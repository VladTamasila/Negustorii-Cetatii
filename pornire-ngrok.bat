@echo off
REM ============================================================
REM  Negustorii Cetatii - pornire tunel ngrok (acces din toata lumea)
REM
REM  PASI INAINTE DE PRIMA RULARE (o singura data):
REM    1. Descarca ngrok: https://ngrok.com/download
REM    2. Fa cont gratuit si copiaza authtoken-ul din dashboard
REM    3. Ruleaza o data:  ngrok config add-authtoken TOKENUL_TAU
REM
REM  CUM SE FOLOSESTE:
REM    1. Porneste intai serverul (pornire-server.bat)
REM    2. Dublu-click pe acest fisier
REM    3. Copiaza linkul "Forwarding https://....ngrok-free.app"
REM       si trimite-l oricui - merge din toata lumea.
REM ============================================================

where ngrok >nul 2>nul
if not %ERRORLEVEL%==0 (
    echo [EROARE] Nu am gasit ngrok in PATH.
    echo Descarca-l de la https://ngrok.com/download si pune ngrok.exe
    echo fie in PATH, fie in acest folder, apoi ruleaza din nou.
    pause
    exit /b 1
)

echo Pornesc tunelul catre http://localhost:8080 ...
echo Linkul public apare mai jos la "Forwarding".
echo.
ngrok http 8080
pause
