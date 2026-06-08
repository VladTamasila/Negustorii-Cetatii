@echo off
REM ============================================================
REM  Negustorii Cetatii - pornire server PHP
REM  Dublu-click pe acest fisier ca sa pornesti aplicatia local.
REM  Apoi deschide:  http://localhost:8080/
REM ============================================================

REM Ne mutam in folderul proiectului (acolo unde e acest .bat)
cd /d "%~dp0"

REM Cautam php: intai in PATH, apoi in locatia implicita XAMPP
where php >nul 2>nul
if %ERRORLEVEL%==0 (
    set "PHP=php"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP=C:\xampp\php\php.exe"
) else (
    echo [EROARE] Nu am gasit php. Porneste XAMPP sau adauga php in PATH.
    pause
    exit /b 1
)

echo ============================================================
echo  Server pornit pe:  http://localhost:8080/
echo  Swagger UI:        http://localhost:8080/docs
echo  Healthcheck:       http://localhost:8080/api/ping
echo.
echo  Lasa aceasta fereastra DESCHISA cat timp te joci.
echo  Inchide cu CTRL+C sau inchizand fereastra.
echo ============================================================
echo.

"%PHP%" -S 0.0.0.0:8080 -t public
pause
