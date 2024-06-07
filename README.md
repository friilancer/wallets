

#  Technical Report: Wallet Functionality Implementation


- [Technical Report](https://docs.google.com/document/d/1AqPiVgDEPk7bjbab6pGOjmQfwXYaRJGZjDa-DgjjHHs/edit?usp=sharing).
- [PostMan Documentation Import Link](https://documenter.getpostman.com/view/11719138/2sA3XJmR5z)

## Introduction

This report outlines the decisions and processes involved in designing and implementing the wallet functionality. The wallet system enables users to perform secure financial transactions, such as sending and receiving funds.

## Schema Design

### Users Table
The `users` table stores user information, including authentication details and administrative status. The `is_super_admin` field distinguishes between regular users and administrators with enhanced capabilities. On login, tokens are generated using sanctum giving different access levels based on user type

- **Columns**: `id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `is_super_admin`
- **Decisions**: The schema includes fields necessary for user authentication and status management.

### Control Balances Table
The `control_balances` table manages administrative funds used for initial user credits and other administrative transactions.

- **Columns**: `id`, `user_id`, `amount`, `created_at`, `updated_at`
- **Decisions**: Linking `control_balances` to `users` ensures traceability of administrative actions.

### Transactions Tables
Separate tables for credit and debit transactions maintain a clear record of all financial activities.

- **Credit Transactions**: `id`, `user_id`, `control_balance_id`, `transaction_reference`, `amount`, `description`, `created_at`, `updated_at`
- **Debit Transactions**: `id`, `user_id`, `control_balance_id`, `transaction_reference`, `amount`, `description`, `created_at`, `updated_at`
- **Decisions**: Splitting transactions into credit and debit tables simplifies querying and improves performance.

### Audit Logs Table
The `audit_logs` table records all significant actions and transactions, providing an audit trail for security and compliance.

- **Columns**: `id`, `user_id`, `action`, `description`, `status`, `error`, `anomaly`, `created_at`, `updated_at`
- **Decisions**: Comprehensive logging ensures accountability and facilitates troubleshooting.

## Choice of Libraries and Frameworks

### Laravel
Laravel was chosen for its robust ecosystem, built-in authentication, and database management capabilities. Key features utilized include Eloquent ORM, middleware, and Sanctum for API authentication.

### Next.js
Next.js was selected for the frontend due to its support for server-side rendering, easy integration with React, and efficient handling of API routes.

### Laravel Sanctum
Sanctum provides a simple solution for API token management and SPA authentication, ensuring secure communication between the frontend and backend.

### Axios
Axios was used for making HTTP requests from the Next.js frontend to the Laravel backend, supporting automatic CSRF token handling.

## Patterns for Safe Concurrency

### Database Transactions
Using database transactions ensures that all operations within a transaction block either complete successfully or roll back entirely, maintaining data consistency.

- **Implementation**: Laravel’s `DB::beginTransaction()`, `DB::commit()`, and `DB::rollBack()` methods are employed to handle critical sections of the code where multiple database operations need to be atomic.

### Row Locking
Row locking prevents race conditions by locking the rows being updated until the transaction completes.

- **Implementation**: Eloquent’s `lockForUpdate()` method is used to lock the user and control balance records during transaction processing, ensuring no other process can modify these records simultaneously.

## Security Measures for Financial Transactions

### CSRF Protection
Cross-Site Request Forgery (CSRF) protection is implemented to prevent unauthorized commands from being transmitted from a user that the website trusts.

- **Implementation**: Laravel’s built-in CSRF protection is leveraged, and CSRF tokens are included in requests from the Next.js frontend.

### Audit Logging
Audit logging tracks all transactions and significant actions, providing a trail that can be reviewed for suspicious activity.

- **Implementation**: Each transaction and action is logged in the `audit_logs` table with details such as user ID, action performed, status, and any anomalies detected.

### Authentication and Authorization
Sanctum is used to manage API tokens and ensure that only authenticated and authorized users can perform transactions.

- **Implementation**: Middleware checks ensure that users are authenticated and authorized to perform specific actions. Routes are protected to restrict access to authenticated users only.

### Data Encryption
Sensitive data, such as passwords, are encrypted to prevent unauthorized access.

- **Implementation**: Passwords are hashed using Laravel’s `Hash::make()` function, and sensitive data can be encrypted and decrypted using Laravel’s built-in encryption methods.

## Conclusion

The design and implementation of the wallet functionality involve careful consideration of schema design, library and framework choices, concurrency patterns, and security measures. By leveraging Laravel’s robust features and integrating seamlessly with a Next.js frontend, the system ensures secure and efficient handling of financial transactions. This report outlines the key decisions and strategies employed to create a reliable and secure wallet system.

