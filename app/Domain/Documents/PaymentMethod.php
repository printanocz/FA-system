<?php

namespace App\Domain\Documents;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Card = 'card';
    case CashOnDelivery = 'cod';
}
