@extends('emails.layouts.product')

@section('content')
    <p style="margin:0 0 16px;"><strong>Намерение:</strong> {{ $payload['intent_label'] ?? '—' }}</p>
    <p style="margin:0 0 8px;"><strong>Имя:</strong> {{ $payload['name'] ?? '—' }}</p>
    <p style="margin:0 0 8px;"><strong>Телефон:</strong> {{ $payload['phone'] ?? '—' }}</p>
    <p style="margin:0 0 8px;"><strong>Email:</strong> {{ $payload['email'] ?? '—' }}</p>
    @if(!empty($payload['preferred_contact_label']))
        <p style="margin:0 0 8px;"><strong>Предпочитаемый канал:</strong> {{ $payload['preferred_contact_label'] }}</p>
    @endif
    <p style="margin:0 0 8px;"><strong>CRM:</strong> #{{ $payload['crm_request_id'] ?? '—' }}</p>
    <p style="margin:16px 0 8px;"><strong>Сообщение</strong></p>
    <p style="margin:0;white-space:pre-wrap;">{{ $payload['message'] ?? '—' }}</p>
    <hr style="border:none;border-top:1px solid #e4e4e7;margin:20px 0;">
    <p style="margin:0;font-size:13px;color:#71717a;">UTM: {{ $payload['utm_source'] ?? '—' }} / {{ $payload['utm_medium'] ?? '—' }} / {{ $payload['utm_campaign'] ?? '—' }}</p>
    <p style="margin:8px 0 0;font-size:13px;color:#71717a;">Страница: {{ $payload['page_url'] ?? '—' }} · IP: {{ $payload['ip'] ?? '—' }}</p>
@endsection
