@echo off
title Cissokho — Demarrage
color 0A
cls

echo.
echo  ============================================
echo    Cissokho ^| Demarrage des serveurs
echo  ============================================
echo.

:: Verifier Node.js
where node >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo  [ERREUR] Node.js non installe.
    echo  Telechargez-le sur https://nodejs.org
    pause
    exit /b 1
)

:: Verifier PHP
if not exist "C:\xampp\php\php.exe" (
    echo  [ERREUR] PHP XAMPP introuvable.
    echo  Installez XAMPP sur https://www.apachefriends.org
    pause
    exit /b 1
)

:: Dossier du projet
set PROJECT=%~dp0
set PROJECT=%PROJECT:~0,-1%

echo  [1/2] Demarrage WhatsApp API (port 3000)...
start "Cissokho — WhatsApp API" cmd /k "cd /d "%PROJECT%\whatsapp-api" && node --env-file=.env index.js"

echo  [2/2] Demarrage serveur PHP (port 8000)...
start "Cissokho — PHP Server" cmd /k "C:\xampp\php\php.exe -S localhost:8000 -t "%PROJECT%""

echo.
echo  Ouverture du navigateur dans 4 secondes...
timeout /t 4 /nobreak >nul

:: Ouvrir l'admin et le QR code
start "" "http://localhost:8000/admin/login.php"
start "" "http://localhost:3000/qr"

echo.
echo  ============================================
echo   Serveurs demarres !
echo.
echo   Admin PHP  : http://localhost:8000/admin
echo   QR WhatsApp: http://localhost:3000/qr
echo  ============================================
echo.
echo  Fermez cette fenetre quand vous avez termine.
pause >nul
