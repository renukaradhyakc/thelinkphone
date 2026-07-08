<?php

namespace App\Services;

class PhoneNumberNormalizer
{
    /**
     * Returns the application's canonical phone format.
     *
     * Current standard:
     *      10-digit Indian mobile number
     *
     * Future:
     *      Can be changed to full E.164 without touching
     *      the rest of the application.
     */
    public function normalize(string $phone): string
    {
        // return substr(preg_replace('/\D/', '', $phone),-10);

        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return '';
        }

        return substr($digits, -10);

        // remove spaces
        // $phone = preg_replace('/\D/', '', $phone);

        // // India example
        // if (strlen($phone) == 10) {
        //     $phone = '91'.$phone;
        // }

        // return '+'.$phone;
    }
}