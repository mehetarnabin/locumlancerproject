<?php

namespace App\Event;

use App\Entity\Invoice;

class InvoiceEvent
{
    public const INVOICE_PAID = 'invoice.paid';

    public function __construct(private Invoice $invoice){}

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }
}