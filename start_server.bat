@echo off
title DoctorConnect Server
echo ======================================================================
echo           DOCTOR CONNECT - ONLINE CONSULTATION PLATFORM
echo ======================================================================
echo.

:: Detect PHP binary (checks WAMP first, then XAMPP, then system PATH)
set PHP_BIN=
if exist "C:\wamp64\bin\php\php8.2.26\php.exe" set PHP_BIN=C:\wamp64\bin\php\php8.2.26\php.exe
if not defined PHP_BIN if exist "C:\wamp64\bin\php\php8.3.14\php.exe" set PHP_BIN=C:\wamp64\bin\php\php8.3.14\php.exe
if not defined PHP_BIN if exist "C:\wamp64\bin\php\php8.1.31\php.exe" set PHP_BIN=C:\wamp64\bin\php\php8.1.31\php.exe
if not defined PHP_BIN if exist "C:\xampp\php\php.exe" set PHP_BIN=C:\xampp\php\php.exe
if not defined PHP_BIN set PHP_BIN=php

echo Using PHP: %PHP_BIN%
echo.

echo [Step 1] Initializing & Seeding Database...
"%PHP_BIN%" database\seed.php

echo.
echo [Step 2] Launching Local Telehealth Server on Port 8000...
echo ======================================================================
echo   Platform URL:   http://localhost:8000
echo   Admin Access:   admin@doctorconnect.com    / admin123
echo   Doctor Access:  dr.smith@doctorconnect.com / doctor123
echo   Patient Access: john.doe@example.com       / patient123
echo ======================================================================
echo.
echo Opening browser... Press Ctrl+C in this window anytime to stop.
echo.

start http://localhost:8000
"%PHP_BIN%" -S localhost:8000
