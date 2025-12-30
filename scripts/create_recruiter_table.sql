-- Create b_recruiter table if it doesn't exist
CREATE TABLE IF NOT EXISTS `b_recruiter` (
  `id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
  `user_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
  `company_name` varchar(255) DEFAULT NULL,
  `speciality` varchar(255) DEFAULT NULL COMMENT 'e.g., Locum Agency, Freelancer',
  `membership_level` varchar(50) DEFAULT 'Silver' COMMENT 'Silver, Gold, Diamond',
  `rating` decimal(3,2) DEFAULT 0.00,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_RECRUITER_USER` FOREIGN KEY (`user_id`) REFERENCES `b_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create b_job_recruiter table if it doesn't exist
CREATE TABLE IF NOT EXISTS `b_job_recruiter` (
  `id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
  `job_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
  `recruiter_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
  `status` varchar(50) DEFAULT 'Assigned' COMMENT 'Assigned, Accepted, Rejected, Closed',
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `assigned_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_JR_JOB` FOREIGN KEY (`job_id`) REFERENCES `b_job` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_JR_RECRUITER` FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

