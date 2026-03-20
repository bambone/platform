<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Booking;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected PricingService $pricingService,
        protected BookingService $bookingService
    ) {}

    /**
     * Booking landing - redirect to catalog or show booking start.
     */
    public function index(): View
    {
        $motorcycles = Motorcycle::query()
            ->where('show_in_catalog', true)
            ->where('status', 'available')
            ->orderBy('sort_order')
            ->get();

        return view('booking.index', compact('motorcycles'));
    }

    /**
     * Show vehicle booking page with date picker and addons.
     */
    public function show(string $slug): View
    {
        $motorcycle = Motorcycle::where('slug', $slug)
            ->where('show_in_catalog', true)
            ->firstOrFail();

        $rentalUnits = $motorcycle->rentalUnits()->where('status', 'active')->get();
        $addons = Addon::where('is_active', true)->orderBy('sort_order')->get();

        return view('booking.show', [
            'motorcycle' => $motorcycle,
            'rentalUnits' => $rentalUnits,
            'addons' => $addons,
        ]);
    }

    /**
     * Check availability and calculate price (AJAX).
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'motorcycle_id' => ['required', 'exists:motorcycles,id'],
            'rental_unit_id' => ['nullable', 'exists:rental_units,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['integer', 'exists:addons,id'],
        ]);

        $motorcycle = Motorcycle::findOrFail($validated['motorcycle_id']);
        $rentalUnits = $motorcycle->rentalUnits()->where('status', 'active')->get();
        $rentalUnit = $validated['rental_unit_id'] ? RentalUnit::find($validated['rental_unit_id']) : $rentalUnits->first();

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

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

        return response()->json([
            'available' => true,
            'price' => $result,
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

        $motorcycle = Motorcycle::find($session['motorcycle_id']);
        if (! $motorcycle) {
            Session::forget('booking_draft');

            return redirect()->route('booking.index')->with('error', 'Транспорт не найден.');
        }

        $addons = collect();
        foreach ($session['addons'] ?? [] as $addonId => $qty) {
            $addon = Addon::find($addonId);
            if ($addon && $qty > 0) {
                $addons->push((object) ['addon' => $addon, 'quantity' => $qty]);
            }
        }

        return view('booking.checkout', [
            'motorcycle' => $motorcycle,
            'draft' => $session,
            'addons' => $addons,
        ]);
    }

    /**
     * Store checkout - create booking.
     */
    public function storeCheckout(Request $request)
    {
        $session = Session::get('booking_draft', []);

        if (empty($session['motorcycle_id']) || empty($session['start_date']) || empty($session['end_date'])) {
            return redirect()->route('booking.index')->with('error', 'Сессия истекла. Выберите даты заново.');
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'customer_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $motorcycle = Motorcycle::findOrFail($session['motorcycle_id']);
        $rentalUnit = isset($session['rental_unit_id']) ? RentalUnit::find($session['rental_unit_id']) : null;

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

        $addonIds = [];
        foreach ($session['addons'] ?? [] as $addonId => $qty) {
            if (is_numeric($qty) && $qty > 0) {
                $addonIds[$addonId] = (int) $qty;
            }
        }

        $target = $rentalUnit ?? $motorcycle;
        $pricing = $this->pricingService->calculatePrice($target, $start, $end, 'daily', $addonIds);

        $booking = $this->bookingService->createPublicBooking([
            'tenant_id' => \currentTenant()->id,
            'motorcycle_id' => $motorcycle->id,
            'rental_unit_id' => $rentalUnit?->id,
            'start_date' => $session['start_date'],
            'end_date' => $session['end_date'],
            'start_at' => $start,
            'end_at' => $end,
            'customer_name' => $validated['customer_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'customer_comment' => $validated['customer_comment'] ?? null,
            'source' => 'public_booking',
            'pricing_snapshot' => $pricing['pricing_snapshot'] ?? $pricing,
            'total_price' => $pricing['total'],
            'deposit_amount' => $pricing['deposit'] ?? 0,
            'addons' => $addonIds,
        ]);

        Session::forget('booking_draft');

        return redirect()->route('booking.thank-you', ['booking' => $booking->booking_number])
            ->with('booking', $booking);
    }

    /**
     * Thank you page after successful booking.
     */
    public function thankYou(Request $request, ?string $booking = null): View
    {
        $bookingModel = $request->session()->get('booking');
        if (! $bookingModel && $booking) {
            $bookingModel = Booking::where('booking_number', $booking)->first();
        }

        return view('booking.thank-you', ['booking' => $bookingModel]);
    }

    /**
     * Store booking draft in session (from vehicle page).
     */
    public function storeDraft(Request $request)
    {
        $validated = $request->validate([
            'motorcycle_id' => ['required', 'exists:motorcycles,id'],
            'rental_unit_id' => ['nullable', 'exists:rental_units,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['integer', 'min:0'],
        ]);

        $rentalUnit = $validated['rental_unit_id'] ?? null;
        $motorcycle = Motorcycle::findOrFail($validated['motorcycle_id']);
        $rentalUnits = $motorcycle->rentalUnits()->where('status', 'active')->get();
        $unit = $rentalUnit ? RentalUnit::find($rentalUnit) : $rentalUnits->first();

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

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

        Session::put('booking_draft', [
            'motorcycle_id' => $validated['motorcycle_id'],
            'rental_unit_id' => $validated['rental_unit_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'addons' => $validated['addons'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'redirect' => route('booking.checkout'),
        ]);
    }
}
