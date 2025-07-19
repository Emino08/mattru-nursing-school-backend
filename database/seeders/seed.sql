USE mattru_nursing;

-- Users
INSERT INTO users (email, password, role, status) VALUES
                                                      ('principal@mattru.edu', '$2y$10$examplehashedpassword', 'principal', 'active'),
                                                      ('applicant1@example.com', '$2y$10$examplehashedpassword', 'applicant', 'active'),
                                                      ('bank@mattru.edu', '$2y$10$examplehashedpassword', 'bank', 'active'),
                                                      ('finance@mattru.edu', '$2y$10$examplehashedpassword', 'finance', 'active');

-- User Profiles
INSERT INTO user_profiles (user_id, first_name, last_name, phone, address, date_of_birth, nationality, emergency_contact) VALUES
    (2, 'John', 'Doe', '+23212345678', '{"country":"Sierra Leone","province":"Southern","district":"Bonthe","town":"Mattru Jong"}', '2000-01-01', 'Sierra Leonean', '{"name":"Jane Doe","phone":"+23298765432"}');

-- Applications
INSERT INTO applications (applicant_id, program_type, application_status, form_data, submission_date) VALUES
    (2, 'undergraduate', 'submitted', '{"personal":{"first_name":"John","last_name":"Doe"},"address":{"country":"Sierra Leone","province":"Southern","district":"Bonthe","town":"Mattru Jong"},"education":[{"school":"Mattru High","from":"2015","to":"2018","qualification":"WASSCE"}]}', '2025-05-23 10:00:00');

-- Payments
INSERT INTO payments (applicant_id, amount, payment_method, transaction_reference, bank_confirmation_pin, payment_status) VALUES
    (1, 100.00, 'bank', 'TXN123456', 'PIN1234', 'pending');

-- Permissions
INSERT INTO permissions (user_id, feature_name, can_create, can_read, can_update, can_delete) VALUES
                                                                                                  (1, 'application_management', 1, 1, 1, 1),
                                                                                                  (1, 'user_management', 1, 1, 1, 1),
                                                                                                  (1, 'analytics_dashboard', 1, 1, 1, 1),
                                                                                                  (4, 'payment_verification', 1, 1, 1, 1),
                                                                                                  (4, 'financial_reports', 1, 1, 1, 1);