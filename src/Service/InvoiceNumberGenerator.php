<?php

namespace App\Service;

use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceNumberGenerator
{
    public function __construct(private EntityManagerInterface $em) {}

    public function generate(): string
    {
        $year = date('Y');
        $prefix = "INV-$year-";

        // Fetch the last invoice for the current year
        $lastInvoice = $this->em->getRepository(Invoice::class)
            ->createQueryBuilder('i')
            ->where('i.invoiceNumber LIKE :pattern')
            ->setParameter('pattern', "$prefix%")
            ->orderBy('i.invoiceNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $nextNumber = 1;

        if ($lastInvoice) {
            // Extract the numeric part and increment it
            preg_match('/INV-\d{4}-(\d+)/', $lastInvoice->getInvoiceNumber(), $matches);
            if (isset($matches[1])) {
                $nextNumber = (int)$matches[1] + 1;
            }
        }

        return sprintf('%s%05d', $prefix, $nextNumber);
    }
}
