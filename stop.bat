@echo off
title Cissokho — Arret
color 0C
echo.
echo  Arret des serveurs Cissokho...
echo.

:: Tuer les processus node (WhatsApp API)
taskkill /F /IM node.exe >nul 2>&1
echo  [OK] WhatsApp API arretee.

:: Tuer les processus PHP (serveur built-in)
taskkill /F /IM php.exe >nul 2>&1
echo  [OK] Serveur PHP arrete.

echo.
echo  Tous les serveurs sont arretes.
timeout /t 2 /nobreak >nul
