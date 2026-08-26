<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Data\CreateBookingData;
use App\Models\Customer;

final class CustomerResolver
{
    public function resolve(CreateBookingData $data): Customer
    {
        if ($data->customerId) {
            return Customer::query()->lockForUpdate()->findOrFail($data->customerId);
        }

        $identity = $data->normalizedEmail()
            ? ['email' => $data->normalizedEmail()]
            : ['phone' => $data->customerPhone];

        return Customer::query()->updateOrCreate($identity, [
            'first_name' => $data->customerName,
            'last_name' => null,
            'email' => $data->normalizedEmail(),
            'phone' => $data->customerPhone,
            'whatsapp' => $data->customerWhatsapp,
            'nationality' => $data->customerNationality,
            'locale' => 'en',
        ]);
    }
}
