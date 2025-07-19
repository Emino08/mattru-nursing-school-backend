CREATE DATABASE IF NOT EXISTS mattru_nursing;
USE mattru_nursing;

CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       email VARCHAR(255) UNIQUE NOT NULL,
                       password VARCHAR(255) NOT NULL,
                       role ENUM('applicant', 'principal', 'finance', 'it', 'bank', 'registrar') NOT NULL,
                       status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
                       verification_token VARCHAR(64),
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE user_profiles (
                               user_id INT PRIMARY KEY,
                               first_name VARCHAR(255),
                               last_name VARCHAR(255),
                               phone VARCHAR(20),
                               address JSON,
                               date_of_birth DATE,
                               nationality VARCHAR(100),
                               emergency_contact JSON,
                               profile_picture VARCHAR(255),
                               FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE applications (
                              id INT AUTO_INCREMENT PRIMARY KEY,
                              applicant_id INT,
                              program_type ENUM('undergraduate', 'diploma'),
                              application_status ENUM('draft', 'submitted', 'under_review', 'interview_scheduled', 'offer_issued', 'rejected') DEFAULT 'draft',
                              form_data JSON,
                              submission_date TIMESTAMP,
                              FOREIGN KEY (applicant_id) REFERENCES users(id)
);

CREATE TABLE documents (
                           id INT AUTO_INCREMENT PRIMARY KEY,
                           application_id INT,
                           file_path VARCHAR(255),
                           file_type ENUM('certificate', 'photo', 'id', 'transcript'),
                           FOREIGN KEY (application_id) REFERENCES applications(id)
);

CREATE TABLE payments (
                          id INT AUTO_INCREMENT PRIMARY KEY,
                          applicant_id INT,
                          amount DECIMAL(10, 2),
                          payment_method ENUM('bank', 'online'),
                          transaction_reference VARCHAR(50),
                          bank_confirmation_pin VARCHAR(50),
                          payment_status ENUM('pending', 'confirmed'),
                          verified_by INT,
                          payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                          FOREIGN KEY (applicant_id) REFERENCES applications(id),
                          FOREIGN KEY (verified_by) REFERENCES users(id)
);

CREATE TABLE permissions (
                             id INT AUTO_INCREMENT PRIMARY KEY,
                             user_id INT,
                             feature_name VARCHAR(255),
                             can_create BOOLEAN DEFAULT 0,
                             can_read BOOLEAN DEFAULT 0,
                             can_update BOOLEAN DEFAULT 0,
                             can_delete BOOLEAN DEFAULT 0,
                             FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE notifications (
                               id INT AUTO_INCREMENT PRIMARY KEY,
                               user_id INT,
                               type ENUM('application_submitted', 'interview', 'offer_letter', 'payment_initiated'),
                               message TEXT,
                               sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                               FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE audit_logs (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT,
                            action VARCHAR(255),
                            details JSON,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES users(id)
);