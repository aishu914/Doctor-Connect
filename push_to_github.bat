@echo off
title Push Doctor-Connect to GitHub
echo ======================================================================
echo           PUSHING DOCTOR-CONNECT TO GITHUB REPOSITORY
echo           Repository: https://github.com/aishu914/Doctor-Connect.git
echo ======================================================================
echo.

git push -u origin main

echo.
if %ERRORLEVEL% equ 0 (
    echo ======================================================================
    echo [SUCCESS] Code successfully pushed to GitHub!
    echo View repository at: https://github.com/aishu914/Doctor-Connect
    echo ======================================================================
) else (
    echo [NOTE] If prompted, sign in via your browser or enter your GitHub Personal Access Token.
)

pause
