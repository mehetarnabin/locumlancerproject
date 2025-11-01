<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class JobIdGenerator
{
    public function __construct(private EntityManagerInterface $em) {}

    public function generate(): string
    {
        $conn = $this->em->getConnection();
        $sql = "SELECT job_id FROM b_job WHERE job_id IS NOT NULL ORDER BY CAST(job_id AS UNSIGNED) DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery()->fetchOne();

        $nextId = $result ? ((int) $result + 1) : 1;

        return (string) $nextId;
    }
}


