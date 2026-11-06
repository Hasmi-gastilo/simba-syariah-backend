<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Str;

class CustomerService
{
    public function generateCustomerNumber(): string
    {
        do {
            $cif = 'CIF' . date('Ymd') . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Customer::where('customer_number', $cif)->exists());

        return $cif;
    }

    public function createCustomer(array $data, int $userId): Customer
    {
        $data['customer_number'] = $this->generateCustomerNumber();
        $data['created_by'] = $userId;
        $data['status'] = $data['status'] ?? 'active';

        return Customer::create($data);
    }

    public function updateCustomer(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer;
    }
}

