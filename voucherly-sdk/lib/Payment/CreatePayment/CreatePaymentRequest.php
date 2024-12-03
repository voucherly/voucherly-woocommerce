<?php

/**
 * Copyright (C) 2024 Voucherly
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @author    Voucherly <info@voucherly.it>
 * @copyright 2024 Voucherly
 * @license   https://opensource.org/license/gpl-3-0/ GNU General Public License version 3 (GPL-3.0)
 */

namespace VoucherlyApi\Payment;

class CreatePaymentRequest
{
    public string $mode = 'Payment';
    public ?string $referenceId = null;
    public ?string $customerId = null;
    public string $customerEmail = '';
    public string $customerFirstName = '';
    public string $customerLastName = '';
    /**
     * @var string
     */
    public $redirectOkUrl = '';
    /**
     * @var string
     */
    public $redirectKoUrl = '';
    /**
     * @var string
     */
    public $callbackUrl = '';
    /**
     * @var string
     */
    public $language = '';
    /**
     * @var string
     */
    public $country = '';
    /**
     * @var string
     */
    public $shippingAddress = '';

    public $metadata = [];

    /**
     * @var CreatePaymentRequestLine[]
     */
    public $lines = [];
    /**
     * @var CreatePaymentRequestDiscount[]
     */
    public $discounts = [];
}
