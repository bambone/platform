Заявка с маркетингового сайта RentBase
================================

Намерение: {{ $payload['intent_label'] ?? '—' }} ({{ $payload['intent'] ?? '—' }})

Имя: {{ $payload['name'] ?? '—' }}
Телефон: {{ $payload['phone'] ?? '—' }}
Email: {{ $payload['email'] ?? '—' }}
@if(!empty($payload['preferred_contact_label']))
Предпочитаемый канал: {{ $payload['preferred_contact_label'] }}
@endif

Сообщение:
{{ $payload['message'] ?? '—' }}

---
UTM: source={{ $payload['utm_source'] ?? '—' }} | medium={{ $payload['utm_medium'] ?? '—' }} | campaign={{ $payload['utm_campaign'] ?? '—' }}
Referer: {{ $payload['page_url'] ?? '—' }}
IP: {{ $payload['ip'] ?? '—' }}
CRM request: {{ $payload['crm_request_id'] ?? '—' }}
