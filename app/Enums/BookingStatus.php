<?php

namespace App\Enums;

enum BookingStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
    case NO_SHOW = 'no_show';
}
