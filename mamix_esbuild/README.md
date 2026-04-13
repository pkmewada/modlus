# MAMIX CRM Project Documentation

Last Updated: 2026-04-09

## 1. Document Purpose

This README is the main working document for both the client and the development team.

It explains:

- what the project is
- what functionality is currently available
- what has been completed so far
- how the project is structured
- how to run the project locally
- what still needs refinement before production

This file should be updated whenever the project grows, changes, or moves to a new phase.

## 2. Project Overview

MAMIX is a CRM-style web application built with PHP, MySQL, Bootstrap 5, and a pre-built admin dashboard theme.

At the current stage, the project is focused on two main areas:

- user authentication and account verification
- lead management inside a protected CRM panel

The application already includes clean routing, shared layouts, session-based access control, lead creation, lead listing, status updates, and OTP-based account verification flow.

## 3. Project Goals

### Client-Facing Goal

Provide a clean and functional CRM panel where authorized users can:

- create an account
- verify their account
- sign in securely
- access a dashboard
- add leads
- manage and update lead records

### Developer-Facing Goal

Maintain a simple, extendable PHP codebase with:

- reusable headers, footers, and sidebar layout files
- central routing
- controller/model separation for authentication
- database-backed lead and user management
- room for future feature expansion

## 4. Current Development Status

| Module | Status | Notes |
| --- | --- | --- |
| Clean routing | Completed | `routes.php` + `.htaccess` provide clean URLs such as `/login`, `/dashboard`, `/leads`. |
| Authentication | Completed with development-mode caveat | Signup, login, logout, and OTP verification are available. |
| Session protection | Completed | Protected CRM pages require login through `includes/auth.php`. |
| Lead management | Completed for core flow | Add lead (modal + AJAX), list leads, export, filter, and update status. |
| Setup module | Completed for initial phase | `/setup` route and Master Setup page are active and protected. |
| Reusable UI system | Completed for shared components | Global toasts, table styling hook, floating labels, form validation, editor, and file upload initializers are centralized. |
| Dashboard UI | Integrated | Dashboard page is available, but current metrics are template/static content. |
| OTP email sending | Partially completed | PHPMailer is integrated, but production mail credentials and delivery configuration still need attention. |
| Automated testing | Not started | `npm test` is only a placeholder right now. |
| Production hardening | Pending | Security, validation, configuration cleanup, and deployment readiness still need improvement. |

## 5. What Has Been Done So Far

The following work is currently present in the project:

### Authentication Flow

- signup page created
- login page created
- OTP verification page created
- logout flow added
- password hashing used during user creation
- session regeneration used after successful login
- protected routes added for authenticated users

### CRM Panel

- dashboard page added
- leads listing page added
- add lead page added
- setup page added
- sidebar navigation connected to CRM routes
- shared header, footer, and sidebar included across protected pages

### Lead Management

- leads are loaded from the database
- leads are shown in a DataTable with export and filters
- CSV export is available
- PDF export is available
- inline lead status change is available
- add lead flow is available in modal with AJAX submit and live table update
- add lead submit button includes loading spinner state
- lead status options currently include `new`, `contacted`, `qualified`, and `closed`

### Setup and Reusable Components

- Setup menu and Master Setup page are available at `/setup`
- Setup page includes reusable UI blocks (table, checkbox/radio, file upload, toast demo, form validation, editor, floating labels)
- toast system is centralized globally with Mamix colored toast variants
- form behavior is centralized through global UI init helpers
- floating labels are auto-applied to supported form fields globally
- common table styling is centralized using a shared table hook (`data-ui-table="mamix"`)

### Routing and Access

- clean URL routing added
- direct `.php` access is redirected to clean routes
- `includes/` and `vendor/` are blocked via `.htaccess`

### Frontend Integration

- admin theme assets integrated through `dist/`
- shared layout structure is working
- esbuild configuration exists for asset workflow

## 6. Current Functional Scope

At this time, the project supports the following user journey:

1. User visits the application.
2. User signs up with name, email, password, and UI-provided phone field.
3. User is redirected to OTP verification.
4. After verification, the user can log in.
5. Logged-in users can access the CRM dashboard.
6. Logged-in users can create leads.
7. Logged-in users can view all leads in a searchable/exportable table.
8. Logged-in users can update the status of existing leads.
9. Logged-in users can access the Setup page and reusable component demos.
10. Logged-in users can log out.

## 7. Current Routes and Screens

| Route | Purpose | Access |
| --- | --- | --- |
| `/` | Redirects to login | Public |
| `/login` | User login page | Public |
| `/signup` | User registration page | Public |
| `/verifyotp` | OTP verification page | Public after signup flow |
| `/dashboard` | CRM dashboard | Authenticated |
| `/leads` | Leads listing and status management | Authenticated |
| `/setup` | Master Setup page and reusable component previews | Authenticated |
| `/addlead` | Add a new lead | Authenticated |
| `/logout` | End user session | Authenticated |

Note: current routing is configured around the `/mamix/` base path in Apache rewrite rules.

## 8. Technology Stack

### Backend

- PHP
- MySQL
- PHPMailer

### Frontend

- Bootstrap 5
- Spruko admin theme assets
- jQuery DataTables
- ApexCharts and other bundled UI libraries from the template

### Tooling

- Node.js
- esbuild
- npm
- Composer
- XAMPP / Apache local environment

## 9. Project Structure

```text
mamix_esbuild/
|-- app/
|   |-- controllers/
|   |-- models/
|   `-- views/
|-- includes/
|-- pages/
|-- dist/
|-- src/
|-- routes.php
|-- .htaccess
|-- package.json
|-- composer.json
`-- esbuild.config.js
```

### Important Directories and Files

- `app/controllers/AuthController.php`
  Handles login, signup, and OTP verification flow.

- `app/models/UserModel.php`
  Handles user database queries.

- `app/views/`
  Contains authentication-related views such as login, signup, and OTP verification.

- `pages/`
  Contains route entry pages and CRM pages such as dashboard, leads, add lead, and logout.

- `includes/db.php`
  Stores database connection configuration and mail constants.

- `includes/auth.php`
  Protects authenticated pages.

- `includes/auth-functions.php`
  Contains helper functions like redirect, input helpers, OTP generation, and development mode handling.

- `includes/sendOtp.php`
  Sends OTP email using PHPMailer and Gmail SMTP.

- `includes/header.php`, `includes/sidebar.php`, `includes/footer.php`
  Shared layout components for CRM pages.

- `routes.php`
  Central route mapping file.

- `.htaccess`
  Handles clean URLs, route rewriting, and access restrictions.

- `dist/`
  Built frontend assets currently used by the PHP pages.

- `src/` and `esbuild.config.js`
  Source assets and build tooling for frontend/theme development.

## 10. Database Expectations

The current codebase expects at least the following tables.

### `users`

Observed fields from the code:

- `id`
- `fullName`
- `email`
- `password`
- `otp`
- `isVerified`

### `leads`

Observed fields from the code:

- `id`
- `fullName`
- `email`
- `phone`
- `source`
- `status`
- `createdAt`

Important note:

- the signup UI currently asks for `phone`, but the current user creation logic does not store it in the `users` table

Recommended next step:

- add a proper SQL schema or migration file to the repository so setup is repeatable for all developers

## 11. Local Setup Guide

### Requirements

- XAMPP or another Apache + MySQL environment
- PHP
- MySQL
- Node.js and npm
- Composer

### Setup Steps

1. Place the project inside the web root.
2. Create the required MySQL database.
3. Update database credentials inside `includes/db.php` if needed.
4. Install Composer dependencies:

```bash
composer install
```

5. Install Node dependencies:

```bash
npm install
```

6. Run the asset build when frontend assets are changed:

```bash
npm run dev
```

7. Open the application in the browser using the configured Apache path.

Current code assumes:

- database host: `localhost`
- database user: `root`
- database password: empty string
- database name: `modlus`

## 12. Configuration Notes

### Development Mode

`DEV_MODE` is currently enabled in `includes/auth-functions.php`.

In development mode:

- OTP email sending is bypassed
- the app still generates OTP values
- verification accepts any 4-digit OTP input and marks the user as verified

Before production release:

- set `DEV_MODE` to `false`
- configure real email credentials
- test the end-to-end OTP flow

### Email Configuration

Email constants are currently defined in `includes/db.php`:

- `GMAIL_USERNAME`
- `GMAIL_APP_PASSWORD`

These placeholder values must be replaced before production email sending can work.

### Routing/Base Path

The current code uses hardcoded base path assumptions such as:

- `/mamix/` in `.htaccess`
- `/mamix/mamix_esbuild/dist/` in layout files

If deployment location changes, these paths must be updated.

## 13. Security and Code Practices Already Present

The project already includes some good backend practices:

- password hashing with `password_hash()`
- password verification with `password_verify()`
- prepared statements for database operations
- session-based route protection
- session regeneration after login
- restricted access to `includes/` and `vendor/`

## 14. Current Gaps and Important Notes

These points are important for both developers and stakeholders because they affect production readiness.

### Functional Gaps

- dashboard values are still static template data, not live CRM analytics
- forgot password is not implemented
- social login buttons are UI-only
- some header/profile links still point to template pages instead of project routes

### Technical Gaps

- signup form includes phone input, but phone is not stored for the user account in current backend logic
- configuration values are hardcoded in PHP files
- no SQL migration/schema file is included yet
- no automated test suite is configured yet

### Production Readiness Improvements Recommended

- move configuration to environment-based settings
- improve validation and duplicate-email handling
- add forgot password flow
- connect dashboard cards and charts to real database data
- add CSRF protection for forms
- add database schema scripts or migrations
- add test coverage for auth and lead workflows

## 15. Recommended Next Phase

Suggested next development priorities:

1. Standardize authentication for production use.
2. Finalize email OTP flow and resend option.
3. Connect dashboard widgets to real lead and user data.
4. Formalize database schema and setup instructions.
5. Improve security hardening and validation.
6. Add testing and deployment documentation.

## 16. Documentation Maintenance Rules

This file should be updated whenever any of the following changes happen:

- a new route or page is added
- a new database table or field is introduced
- any existing flow changes
- a feature moves from pending to completed
- production configuration changes
- deployment steps change

When updating this README, always review these sections:

- `Last Updated`
- `Current Development Status`
- `What Has Been Done So Far`
- `Current Routes and Screens`
- `Database Expectations`
- `Current Gaps and Important Notes`
- `Recommended Next Phase`

## 17. Change Log

### 2026-04-09

- created initial project documentation README
- documented current auth, lead management, routing, and setup flow
- recorded current implementation gaps and next recommended steps
- added Setup menu and `/setup` route/page (Master Setup)
- converted Add Lead flow to modal + AJAX with live table update and toast feedback
- removed Add Lead menu item from sidebar (route retained)
- added leads filters for status/source and sequence-based SNo behavior
- centralized Mamix colored toast system globally and integrated Setup + Leads
- centralized reusable UI initializers (form validation, file upload, Quill editor, toast button binding)
- added global floating-label auto-application for supported form fields
- centralized common table styling hook and aligned Setup/Leads tables

## 18. Summary

MAMIX currently has a solid base for a CRM project:

- authentication flow is present
- lead management core flow is working
- protected CRM pages are available
- routing and shared layout are set up

The next major step is to convert the current development-ready foundation into a production-ready CRM by tightening configuration, aligning the OTP workflow, and connecting dashboard data to real business metrics.
