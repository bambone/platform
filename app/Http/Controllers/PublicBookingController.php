<?php

namespace App\Http\Controllers;

use App\Booking\PublicBookingCheckoutException;
use App\Booking\PublicBookingMotorcyclePolicy;
use App\ContactChannels\TenantContactChannelsStore;
use App\ContactChannels\VisitorContactPayloadBuilder;
use App\Http\Requests\StorePublicBookingCheckoutRequest;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Models\TenantLocation;
use App\Money\MoneyBindingRegistry;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\Catalog\MotorcycleLocationCatalogService;
use App\Services\Tenancy\TenantMotoRentalLegalUrls;
use App\Services\Catalog\TenantPublicCatalogLocationService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected PricingService $pricingService,
        protected BookingService $bookingService,
        protected TenantPublicCatalogLocationService $catalogLocation,
        protected MotorcycleLocationCatalogService $motorcycleLocationCatalog,
        protected TenantMotoRentalLegalUrls $motoRentalLegalUrls,
    ) {}

    /**
     * Booking landing - redirect to catalog or show booking start.
     */
    public function index(): View
    {
        $motorcyclesQuery = PublicBookingMotorcyclePolicy::constrainEligibleForPublicBooking(Motorcycle::query());
        $selectedCatalogLocation = $this->catalogLocation->resolve();
        if ($selectedCatalogLocation !== null) {
            $this->motorcycleLocationCatalog->scopeMotorcyclesVisibleAtLocation($motorcyclesQuery, $selectedCatalogLocation);
        }
        $motorcycles = $motorcyclesQuery->orderBy('sort_order')->get();

        return tenant_view('booking.index', [
            'motorcycles' => $motorcycles,
            'catalogLocations' => $this->catalogLocation->activeLocationsForCurrentTenant(),
            'selectedCatalogLocation' => $selectedCatalogLocation,
            'catalogLocationFormAction' => route('booking.index'),
        ]);
    }

    /**
     * Show vehicle booking page with date picker and addons.
     */
    public function show(string $slug): View
    {
        $tenant = currentTenant();
        abort_if($tenant === null, 404);
        $tenantId = (int) $tenant->id;

        $motorcycle = Motorcycle::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->firstOrFail();
        if (! PublicBookingMotorcyclePolicy::isAllowedForPublicBooking($motorcycle)) {
            abort(404);
        }

        $selectedCatalogLocation = $this->catalogLocation->resolve();
        $visibleAtSelectedLocation = $selectedCatalogLocation === null
            || $this->motorcycleLocationCatalog->isMotorcycleVisibleAtLocation($motorcycle, $selectedCatalogLocation);

        $rentalUnits = $this->activeRentalUnitsForPublicBooking($motorcycle);
        $addons = Addon::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('tenant.booking.show', [
            'motorcycle' => $motorcycle,
            'rentalUnits' => $rentalUnits,
            'addons' => $addons,
            'catalogLocations' => $this->catalogLocation->activeLocationsForCurrentTenant(),
            'selectedCatalogLocation' => $selectedCatalogLocation,
            'catalogLocationFormAction' => route('booking.index'),
            'visibleAtSelectedLocation' => $visibleAtSelectedLocation,
        ]);
    }

    /**
     * Check availability and calculate price (AJAX).
     */
    public function calculate(Request $request)
    {
        $tenant = currentTenant();
        abort_if($tenant === null, 404);
        $tenantId = (int) $tenant->id;

        $validated = $request->validate([
            'motorcycle_id' => ['required', Rule::exists('motorcycles', 'id')->where('tenant_id', $tenantId)],
            'rental_unit_id' => ['nullable', Rule::exists('rental_units', 'id')->where('tenant_id', $tenantId)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'addons' => ['nullable', 'array'],
        ]);
        $this->validatePublicBookingAddonsMap($tenantId, $validated['addons'] ?? []);

        $motorcycle = Motorcycle::findOrFail($validated['motorcycle_id']);

        if ($response = $this->assertMotorcycleAllowedForPublicBooking($motorcycle)) {
            return $response;
        }

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

        $rentalUnits = $this->activeRentalUnitsForPublicBooking($motorcycle);
        $rentalUnitIdRaw = $validated['rental_unit_id'] ?? null;
        $rentalUnitId = is_numeric($rentalUnitIdRaw) ? (int) $rentalUnitIdRaw : 0;
        $rentalUnit = $this->resolveRentalUnitForCalculateOrDraft($motorcycle, $rentalUnits, $rentalUnitId > 0 ? $rentalUnitId : null, $start, $end);
        if ($rentalUnit instanceof JsonResponse) {
            return $rentalUnit;
        }

        $available = true;
        if ($rentalUnit) {
            $available = $this->availabilityService->isAvailable($rentalUnit, $start, $end);
        } else {
            $available = $this->bookingService->isAvailableForMotorcycle($motorcycle->id, $validated['start_date'], $validated['end_date']);
        }

        if (! $available) {
            return response()->json([
                'available' => false,
                'message' => 'Выбранные даты заняты. Попробуйте другие даты.',
            ]);
        }

        $target = $rentalUnit ?? $motorcycle;
        $addonIds = [];
        foreach ($validated['addons'] ?? [] as $addonId => $qty) {
            if (is_numeric($qty) && $qty > 0) {
                $addonIds[$addonId] = (int) $qty;
            }
        }

        $result = $this->pricingService->calculatePrice($target, $start, $end, 'daily', $addonIds);

        $tenant = currentTenant();
        if ($tenant !== null) {
            $result['total_formatted'] = tenant_money_format((int) $result['total'], MoneyBindingRegistry::BOOKING_TOTAL_PRICE, $tenant);
            $result['deposit_formatted'] = tenant_money_format((int) $result['deposit'], MoneyBindingRegistry::BOOKING_DEPOSIT_AMOUNT, $tenant);
            $result['base_price_formatted'] = tenant_money_format((int) $result['base_price'], MoneyBindingRegistry::BOOKING_TOTAL_PRICE, $tenant);
            $result['addons_total_formatted'] = tenant_money_format((int) $result['addons_total'], MoneyBindingRegistry::BOOKING_TOTAL_PRICE, $tenant);
        }

        return response()->json([
            'available' => true,
            'price' => $result,
            'rental_unit_id' => $rentalUnit?->id,
        ]);
    }

    /**
     * Show checkout form.
     */
    public function checkout(Request $request)
    {
        $session = Session::get('booking_draft', []);

        if (empty($session['motorcycle_id']) || empty($session['start_date']) || empty($session['end_date'])) {
            return redirect()->route('booking.index')->with('error', 'Сначала выберите транспорт и даты.');
        }

        $tenant = currentTenant();
        abort_if($tenant === null, 404);
        $tenantId = (int) $tenant->id;

        $motorcycle = Motorcycle::query()
            ->where('tenant_id', $tenantId)
            ->whereKey((int) $session['motorcycle_id'])
            ->first();
        if (! $motorcycle) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Транспорт не найден.');
        }

        if (! PublicBookingMotorcyclePolicy::isAllowedForPublicBooking($motorcycle)) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Эта модель недоступна для бронирования.');
        }

        $selectedCatalogLocation = $this->resolveCatalogLocationForBookingDraft($session);
        if ($this->bookingDraftCatalogLocationInvalid($session, $selectedCatalogLocation)) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Точка выдачи больше не доступна. Начните бронирование заново.');
        }
        if ($selectedCatalogLocation !== null
            && ! $this->motorcycleLocationCatalog->isMotorcycleVisibleAtLocation($motorcycle, $selectedCatalogLocation)) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Модель недоступна в выбранной точке. Выберите другую локацию или весь каталог.');
        }

        $allowedUnits = $this->activeRentalUnitsForPublicBooking($motorcycle, $selectedCatalogLocation);
        if ($motorcycle->uses_fleet_units && empty($session['rental_unit_id'])) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Сессия бронирования устарела: не выбрана единица парка. Выберите даты на странице модели ещё раз.');
        }
        if ($motorcycle->uses_fleet_units) {
            $sessionUnit = RentalUnit::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((int) $session['rental_unit_id'])
                ->first();
            if (! $sessionUnit || $sessionUnit->motorcycle_id !== $motorcycle->id || ! $allowedUnits->contains('id', (int) $session['rental_unit_id'])) {
                Session::forget('booking_draft');

                return redirect()->route('booking.index')->with('error', 'Выбранная единица парка недоступна. Оформите бронь заново.');
            }
        }

        $addons = collect();
        foreach ($session['addons'] ?? [] as $addonId => $qty) {
            $addon = Addon::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((int) $addonId)
                ->where('is_active', true)
                ->first();
            if ($addon && $qty > 0) {
                $addons->push((object) ['addon' => $addon, 'quantity' => $qty]);
            }
        }

        $preferredChannelFormOptions = app(TenantContactChannelsStore::class)->publicFormPreferredOptions($tenant->id);
        $rentalLegalUrls = $this->motoRentalLegalUrls->forTenant($tenant);

        return view('tenant.booking.checkout', [
            'motorcycle' => $motorcycle,
            'draft' => $session,
            'addons' => $addons,
            'preferredChannelFormOptions' => $preferredChannelFormOptions,
            'rentalLegalUrls' => $rentalLegalUrls,
        ]);
    }

    /**
     * Store checkout - create booking.
     */
    public function storeCheckout(StorePublicBookingCheckoutRequest $request, VisitorContactPayloadBuilder $contactPayloadBuilder)
    {
        $session = Session::get('booking_draft', []);

        if (empty($session['motorcycle_id']) || empty($session['start_date']) || empty($session['end_date'])) {
            return redirect()->route('booking.index')->with('error', 'Сессия истекла. Выберите даты заново.');
        }

        $validated = $request->validated();
        $tenant = currentTenant();
        abort_if($tenant === null, 404);
        $tenantId = (int) $tenant->id;

        $contact = $contactPayloadBuilder->build($tenant->id, [
            'phone' => $validated['phone'],
            'preferred_contact_channel' => $validated['preferred_contact_channel'],
            'preferred_contact_value' => $validated['preferred_contact_value'] ?? null,
        ]);

        $motorcycle = Motorcycle::query()
            ->where('tenant_id', $tenantId)
            ->whereKey((int) $session['motorcycle_id'])
            ->first();
        if ($motorcycle === null) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Транспорт не найден. Оформите бронь заново.');
        }

        if (! PublicBookingMotorcyclePolicy::isAllowedForPublicBooking($motorcycle)) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Эта модель недоступна для бронирования.');
        }

        $selectedCatalogLocation = $this->resolveCatalogLocationForBookingDraft($session);
        if ($this->bookingDraftCatalogLocationInvalid($session, $selectedCatalogLocation)) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Точка выдачи больше не доступна. Начните бронирование заново.');
        }
        if ($selectedCatalogLocation !== null
            && ! $this->motorcycleLocationCatalog->isMotorcycleVisibleAtLocation($motorcycle, $selectedCatalogLocation)) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Модель недоступна в выбранной точке.');
        }

        $allowedUnits = $this->activeRentalUnitsForPublicBooking($motorcycle, $selectedCatalogLocation);
        if ($motorcycle->uses_fleet_units && empty($session['rental_unit_id'])) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Сессия бронирования устарела: не выбрана единица парка. Выберите даты на странице модели ещё раз.');
        }
        $rentalUnit = null;
        if ($motorcycle->uses_fleet_units) {
            $rentalUnit = RentalUnit::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((int) $session['rental_unit_id'])
                ->first();
            if ($rentalUnit === null || $rentalUnit->motorcycle_id !== $motorcycle->id || ! $allowedUnits->contains('id', $rentalUnit->id)) {
                Session::forget('booking_draft');

                return redirect()->route('booking.index')->with('error', 'Единица парка недоступна в выбранной точке.');
            }
        }

        $start = Carbon::parse($session['start_date'])->startOfDay();
        $end = Carbon::parse($session['end_date'])->endOfDay();

        $available = true;
        if ($rentalUnit) {
            $available = $this->availabilityService->isAvailable($rentalUnit, $start, $end);
        } else {
            $available = $this->bookingService->isAvailableForMotorcycle($motorcycle->id, $session['start_date'], $session['end_date']);
        }

        if (! $available) {
            return redirect()->route('booking.index')->with('error', 'Выбранные даты больше недоступны.');
        }

        try {
            $this->validatePublicBookingAddonsMap($tenantId, $session['addons'] ?? []);
        } catch (ValidationException $e) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Черновик бронирования содержит недопустимые дополнения. Оформите бронь заново.');
        }

        $addonIds = [];
        foreach ($session['addons'] ?? [] as $addonId => $qty) {
            if (is_numeric($qty) && $qty > 0) {
                $addonIds[$addonId] = (int) $qty;
            }
        }

        try {
            $booking = $this->bookingService->createPublicBooking([
                'tenant_id' => $tenant->id,
                'motorcycle_id' => $motorcycle->id,
                'rental_unit_id' => $rentalUnit?->id,
                'public_catalog_location_id' => $selectedCatalogLocation?->id,
                'start_date' => $session['start_date'],
                'end_date' => $session['end_date'],
                'start_at' => $start,
                'end_at' => $end,
                'customer_name' => $validated['customer_name'],
                'phone' => $validated['phone'],
                'preferred_contact_channel' => $contact['preferred_contact_channel'],
                'preferred_contact_value' => $contact['preferred_contact_value'],
                'visitor_contact_channels_json' => $contact['visitor_contact_channels_json'],
                'legal_acceptances_json' => $this->motoRentalLegalUrls->acceptanceSnapshotForBooking($tenant),
                'email' => $validated['email'] ?? null,
                'customer_comment' => $validated['customer_comment'] ?? null,
                'source' => 'public_booking',
                'addons' => $addonIds,
            ]);
        } catch (\InvalidArgumentException $e) {
            $forgetDraft = $e instanceof PublicBookingCheckoutException && $e->forgetDraft;
            $toCatalog = $e instanceof PublicBookingCheckoutException && $e->redirectToCatalog;
            if ($forgetDraft) {
                Session::forget('booking_draft');
            }

            return redirect()
                ->route($toCatalog ? 'booking.index' : 'booking.checkout')
                ->with('error', $e->getMessage());
        }

        Session::forget('booking_draft');

        return redirect()->route('booking.thank-you', ['booking' => $booking->booking_number])
            ->with('booking', $booking);
    }

    /**
     * Thank you page after successful booking.
     */
    public function thankYou(Request $request, ?string $booking = null): View
    {
        $tenant = currentTenant();
        $bookingModel = null;

        $fromSession = $request->session()->get('booking');
        if ($fromSession instanceof Booking
            && $tenant !== null
            && (int) $fromSession->tenant_id === (int) $tenant->id) {
            $bookingModel = $fromSession;
        }

        if ($bookingModel === null && $booking !== null && $booking !== '' && $tenant !== null) {
            $bookingModel = Booking::query()
                ->where('tenant_id', $tenant->id)
                ->where('booking_number', $booking)
                ->first();
        }

        return view('tenant.booking.thank-you', ['booking' => $bookingModel]);
    }

    /**
     * Store booking draft in session (from vehicle page).
     */
    public function storeDraft(Request $request)
    {
        $tenant = currentTenant();
        abort_if($tenant === null, 404);
        $tenantId = (int) $tenant->id;

        $validated = $request->validate([
            'motorcycle_id' => ['required', Rule::exists('motorcycles', 'id')->where('tenant_id', $tenantId)],
            'rental_unit_id' => ['nullable', Rule::exists('rental_units', 'id')->where('tenant_id', $tenantId)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'addons' => ['nullable', 'array'],
        ]);
        $this->validatePublicBookingAddonsMap($tenantId, $validated['addons'] ?? []);

        $motorcycle = Motorcycle::findOrFail($validated['motorcycle_id']);

        if ($response = $this->assertMotorcycleAllowedForPublicBooking($motorcycle)) {
            return $response;
        }

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

        $rentalUnits = $this->activeRentalUnitsForPublicBooking($motorcycle);
        $rentalUnitIdRaw = $validated['rental_unit_id'] ?? null;
        $rentalUnitId = is_numeric($rentalUnitIdRaw) ? (int) $rentalUnitIdRaw : 0;
        $resolved = $this->resolveRentalUnitForCalculateOrDraft($motorcycle, $rentalUnits, $rentalUnitId > 0 ? $rentalUnitId : null, $start, $end);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        $unit = $resolved;

        $available = true;
        if ($unit) {
            $available = $this->availabilityService->isAvailable($unit, $start, $end);
        } else {
            $available = $this->bookingService->isAvailableForMotorcycle($motorcycle->id, $validated['start_date'], $validated['end_date']);
        }

        if (! $available) {
            return response()->json([
                'success' => false,
                'message' => 'Выбранные даты заняты.',
            ], 422);
        }

        $snapshotLocation = $this->catalogLocation->resolve($request);

        Session::put('booking_draft', [
            'motorcycle_id' => $validated['motorcycle_id'],
            'rental_unit_id' => $motorcycle->uses_fleet_units ? $unit?->id : null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'addons' => $validated['addons'] ?? [],
            'public_catalog_location_id' => $snapshotLocation?->id,
        ]);

        return response()->json([
            'success' => true,
            'redirect' => route('booking.checkout'),
        ]);
    }

    /**
     * {@code addons} — map {@code addonId => qty}. Laravel's {@code addons.*} validates values, not keys;
     * this enforces tenant-scoped addon IDs and non-negative integer quantities.
     *
     * @param  array<int|string, mixed>  $addons
     *
     * @throws ValidationException
     */
    private function validatePublicBookingAddonsMap(int $tenantId, array $addons): void
    {
        if ($addons === []) {
            return;
        }

        $ids = [];
        foreach ($addons as $addonIdKey => $qty) {
            if (! is_numeric($addonIdKey)) {
                throw ValidationException::withMessages([
                    'addons' => ['Некорректный идентификатор дополнения.'],
                ]);
            }

            $addonId = (int) $addonIdKey;
            if ($addonId < 1) {
                throw ValidationException::withMessages([
                    'addons' => ['Некорректный идентификатор дополнения.'],
                ]);
            }

            $qtyInt = filter_var($qty, FILTER_VALIDATE_INT);
            if ($qtyInt === false) {
                throw ValidationException::withMessages([
                    'addons' => ['Некорректное количество дополнения.'],
                ]);
            }

            if ($qtyInt < 0) {
                throw ValidationException::withMessages([
                    'addons' => ['Некорректное количество дополнения.'],
                ]);
            }

            $ids[] = $addonId;
        }

        $ids = array_values(array_unique($ids));
        $validIds = Addon::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
        $validSet = array_flip($validIds);

        foreach ($ids as $id) {
            if (! isset($validSet[$id])) {
                throw ValidationException::withMessages([
                    'addons' => ['Выбрано недоступное или отключённое дополнение.'],
                ]);
            }
        }
    }

    /**
     * @return Collection<int, RentalUnit>
     */
    private function activeRentalUnitsForPublicBooking(Motorcycle $motorcycle, ?TenantLocation $catalogLocation = null): Collection
    {
        $loc = $catalogLocation ?? $this->catalogLocation->resolve();

        return $this->motorcycleLocationCatalog
            ->rentalUnitsQueryForPublic($motorcycle, $loc)
            ->orderBy('id')
            ->get();
    }

    /**
     * Локация каталога из снимка черновика; без ключа — прежнее поведение (текущая remembered/query).
     */
    private function resolveCatalogLocationForBookingDraft(array $session): ?TenantLocation
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return null;
        }

        if (! array_key_exists('public_catalog_location_id', $session)) {
            return $this->catalogLocation->resolve();
        }

        $raw = $session['public_catalog_location_id'];
        if ($raw === null || $raw === '' || (int) $raw < 1) {
            return null;
        }

        return TenantLocation::query()
            ->where('tenant_id', (int) $tenant->id)
            ->whereKey((int) $raw)
            ->where('is_active', true)
            ->first();
    }

    /**
     * В черновике сохранён id точки, но строка в БД недоступна (снята с публикации / удалена).
     */
    private function bookingDraftCatalogLocationInvalid(array $session, ?TenantLocation $resolved): bool
    {
        if (! array_key_exists('public_catalog_location_id', $session)) {
            return false;
        }

        $rid = (int) ($session['public_catalog_location_id'] ?? 0);

        return $rid > 0 && $resolved === null;
    }

    private function assertMotorcycleAllowedForPublicBooking(Motorcycle $motorcycle): ?JsonResponse
    {
        if (! PublicBookingMotorcyclePolicy::isAllowedForPublicBooking($motorcycle)) {
            return response()->json([
                'available' => false,
                'message' => 'Эта модель недоступна для онлайн-бронирования.',
            ], 422);
        }

        $loc = $this->catalogLocation->resolve();
        if ($loc !== null && ! $this->motorcycleLocationCatalog->isMotorcycleVisibleAtLocation($motorcycle, $loc)) {
            return response()->json([
                'available' => false,
                'message' => 'Модель недоступна в выбранной точке. Смените локацию или откройте весь каталог.',
            ], 422);
        }

        return null;
    }

    /**
     * @param  Collection<int, RentalUnit>  $allowedUnits
     */
    private function resolveRentalUnitForCalculateOrDraft(Motorcycle $motorcycle, Collection $allowedUnits, ?int $rentalUnitId, Carbon $rangeStart, Carbon $rangeEnd): RentalUnit|JsonResponse|null
    {
        if (! $motorcycle->uses_fleet_units) {
            return null;
        }

        if ($allowedUnits->isEmpty()) {
            return response()->json([
                'available' => false,
                'message' => 'Нет доступных единиц парка для выбранной точки.',
            ], 422);
        }

        if ($rentalUnitId !== null && $rentalUnitId > 0) {
            $rentalUnit = RentalUnit::query()
                ->where('tenant_id', (int) $motorcycle->tenant_id)
                ->whereKey($rentalUnitId)
                ->first();
            if (! $rentalUnit || $rentalUnit->motorcycle_id !== $motorcycle->id) {
                return response()->json([
                    'available' => false,
                    'message' => 'Указана некорректная единица парка.',
                ], 422);
            }
            if (! $allowedUnits->contains('id', $rentalUnit->id)) {
                return response()->json([
                    'available' => false,
                    'message' => 'Эта единица недоступна в выбранной точке.',
                ], 422);
            }

            return $rentalUnit;
        }

        foreach ($allowedUnits as $candidate) {
            if ($this->availabilityService->isAvailable($candidate, $rangeStart, $rangeEnd)) {
                return $candidate;
            }
        }

        return response()->json([
            'available' => false,
            'message' => 'Выбранные даты заняты. Попробуйте другие даты.',
        ]);
    }
}
