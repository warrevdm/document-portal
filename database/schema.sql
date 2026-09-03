SET NAMES utf8mb4;
SET time_zone = '+02:00';

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('seller','processor','manager','admin') NOT NULL DEFAULT 'seller',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_number VARCHAR(80) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  street VARCHAR(190) NULL,
  postal_code VARCHAR(20) NULL,
  city VARCHAR(100) NULL,
  company_name VARCHAR(190) NULL,
  vat_number VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customer_name(last_name, first_name),
  INDEX idx_customer_email(email),
  INDEX idx_customer_number(customer_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lease_partners (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  portal_url VARCHAR(255) NULL,
  processing_email VARCHAR(190) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO lease_partners(name) VALUES
('KBC'),('Cyclis'),('O2O'),('B2Bike'),('Ubike'),('Lease a Bike'),('VDW Lease'),('Cycle Valley');

CREATE TABLE lease_cases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_number VARCHAR(40) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NOT NULL,
  lease_partner_id BIGINT UNSIGNED NOT NULL,
  seller_id BIGINT UNSIGNED NOT NULL,
  processor_id BIGINT UNSIGNED NULL,
  status ENUM('concept','document_uploaded','ready_for_signing','waiting_for_customer','signed','ready_for_processing','in_processing','missing_info','processed','completed','cancelled','expired') NOT NULL DEFAULT 'concept',
  priority ENUM('normal','high','urgent') NOT NULL DEFAULT 'normal',
  external_reference VARCHAR(150) NULL,
  employer VARCHAR(190) NULL,
  lease_duration SMALLINT UNSIGNED NULL,
  bike_brand VARCHAR(120) NOT NULL,
  bike_model VARCHAR(190) NOT NULL,
  bike_sku VARCHAR(100) NULL,
  bike_size VARCHAR(80) NULL,
  bike_color VARCHAR(100) NULL,
  serial_number VARCHAR(120) NULL,
  total_amount DECIMAL(10,2) NULL,
  notes TEXT NULL,
  signed_at DATETIME NULL,
  processing_started_at DATETIME NULL,
  processed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_case_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
  CONSTRAINT fk_case_partner FOREIGN KEY(lease_partner_id) REFERENCES lease_partners(id),
  CONSTRAINT fk_case_seller FOREIGN KEY(seller_id) REFERENCES users(id),
  CONSTRAINT fk_case_processor FOREIGN KEY(processor_id) REFERENCES users(id),
  INDEX idx_case_status(status),
  INDEX idx_case_customer(customer_id),
  INDEX idx_case_seller(seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lease_case_id BIGINT UNSIGNED NOT NULL,
  type ENUM('order_form','approval','identity','invoice','accessories','contract','audit_certificate','other') NOT NULL DEFAULT 'order_form',
  title VARCHAR(190) NOT NULL,
  status ENUM('draft','uploaded','signed','locked','superseded') NOT NULL DEFAULT 'uploaded',
  required TINYINT(1) NOT NULL DEFAULT 1,
  current_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_document_case FOREIGN KEY(lease_case_id) REFERENCES lease_cases(id) ON DELETE CASCADE,
  INDEX idx_document_case(lease_case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  sha256_hash CHAR(64) NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_version_document FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_version_user FOREIGN KEY(uploaded_by) REFERENCES users(id),
  UNIQUE KEY uq_document_version(document_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE signature_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lease_case_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  signer_name VARCHAR(190) NOT NULL,
  signer_email VARCHAR(190) NOT NULL,
  status ENUM('pending','opened','signed','expired','revoked') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NOT NULL,
  opened_at DATETIME NULL,
  accepted_at DATETIME NULL,
  signed_at DATETIME NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  page_number INT UNSIGNED NOT NULL DEFAULT 1,
  position_x DECIMAL(7,4) NOT NULL DEFAULT 0.60,
  position_y DECIMAL(7,4) NOT NULL DEFAULT 0.78,
  width_ratio DECIMAL(7,4) NOT NULL DEFAULT 0.28,
  height_ratio DECIMAL(7,4) NOT NULL DEFAULT 0.10,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_signature_case FOREIGN KEY(lease_case_id) REFERENCES lease_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_signature_document FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE CASCADE,
  INDEX idx_signature_status(status),
  INDEX idx_signature_case(lease_case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE signatures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  signature_request_id BIGINT UNSIGNED NOT NULL UNIQUE,
  signature_path VARCHAR(255) NOT NULL,
  document_hash_before CHAR(64) NOT NULL,
  document_hash_after CHAR(64) NOT NULL,
  signed_document_version_id BIGINT UNSIGNED NOT NULL,
  signed_at DATETIME NOT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_signature_request FOREIGN KEY(signature_request_id) REFERENCES signature_requests(id),
  CONSTRAINT fk_signature_version FOREIGN KEY(signed_document_version_id) REFERENCES document_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE case_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lease_case_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comment_case FOREIGN KEY(lease_case_id) REFERENCES lease_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_comment_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lease_case_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(50) NULL,
  to_status VARCHAR(50) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  reason VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_case FOREIGN KEY(lease_case_id) REFERENCES lease_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user FOREIGN KEY(changed_by) REFERENCES users(id),
  INDEX idx_history_case(lease_case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lease_case_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_type ENUM('user','customer','system') NOT NULL DEFAULT 'user',
  event_type VARCHAR(80) NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  metadata JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_case FOREIGN KEY(lease_case_id) REFERENCES lease_cases(id) ON DELETE SET NULL,
  CONSTRAINT fk_audit_user FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_case(lease_case_id),
  INDEX idx_audit_event(event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
