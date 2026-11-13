# MateClass - Attendance Management System

## About the Project

MateClass is a simple attendance management system made using PHP, MySQL, HTML, CSS, and JavaScript. This system helps manage student information and record their daily attendance.

## Features

- Login and logout
- Dashboard for viewing attendance information
- Add, edit, delete, and search student records
- Record students' attendance
- View attendance records
- Generate attendance reports
- Export attendance data

## Tools and Technologies

- PHP
- MySQL
- HTML
- CSS
- JavaScript
- XAMPP
- Font Awesome

## Requirements

Before running the project, make sure you have:

- XAMPP installed
- Apache and MySQL running
- A web browser
- PHP and MySQL available through XAMPP

## How to Install

1. Download or extract the project.
2. Copy the `masterclass-system` folder.
3. Paste it inside the `htdocs` folder of XAMPP.
4. Open XAMPP and start Apache and MySQL.
5. Open phpMyAdmin by visiting `http://localhost/phpmyadmin`.
6. Create a database named `attendance_db`.
7. Import the `database.sql` file if it contains the database tables. If it is empty, create the required tables first.
8. Check the database settings in `config/database.php`.
9. Open the project in your browser:

   `http://localhost/masterclass-system/`

## Database Setup

The project uses a MySQL database named `attendance_db`.

Check `config/database.php` and make sure the database name, username, password, and host match your XAMPP settings.

The main tables used by the system are:

- `users` - stores login account information
- `students` - stores student information
- `attendance` - stores attendance records

If the database tables are not included in `database.sql`, they need to be created before using the system.

## How to Use

1. Open MateClass in your browser.
2. Log in using an existing account.
3. Go to the Students page to manage student information.
4. Go to the Attendance page to record attendance.
5. Open Records to view saved attendance.
6. Open Reports to check attendance summaries.

## Project Folder Structure

```text
masterclass-system/
├── api/
├── assets/
│   ├── css/
│   └── images/
├── config/
├── export/
├── js/
├── attendance.php
├── dashboard.php
├── database.sql
├── index.php
├── logout.php
├── records.php
├── reports.php
└── students.php
```

## Developers

MateClass - Attendance Management System
