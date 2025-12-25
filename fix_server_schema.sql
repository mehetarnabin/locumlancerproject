-- Run these commands in your remote database (e.g., via phpMyAdmin) to fix the "Column not found" error.

-- 1. Add the missing application_id column
ALTER TABLE b_message ADD application_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)';

-- 2. Add an index for performance
CREATE INDEX IDX_B_MESSAGE_APPLICATION_ID ON b_message (application_id);

-- 3. Add the foreign key constraint to link it to the application table
ALTER TABLE b_message ADD CONSTRAINT FK_B_MESSAGE_APPLICATION_ID FOREIGN KEY (application_id) REFERENCES b_application (id);

-- 4. Add the missing notification_preferences column to b_employer
ALTER TABLE b_employer ADD notification_preferences JSON DEFAULT NULL COMMENT '(DC2Type:json)';
