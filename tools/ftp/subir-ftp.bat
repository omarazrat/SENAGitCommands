@echo off
setlocal EnableExtensions

rem ============================================================
rem  SUBIR A INFINITYFREE - ARRASTRANDO ARCHIVOS O CARPETAS
rem
rem  Suelta archivos (o una carpeta entera) sobre este icono
rem  y se suben por FTP a la carpeta remota configurada.
rem
rem  Credenciales: se cargan desde var\scripts\setEnv.bat
rem  (FTP_USER, FTP_PWD, FTP_HOST). No defines contrasenas
rem  en este archivo.
rem
rem  Carpeta remota por defecto: /htdocs
rem  Puedes cambiarla con la variable FTP_DIR en tu entorno
rem  (ej. set FTP_DIR=/htdocs/app) o editando el valor por
rem  defecto en upload.ps1.
rem ============================================================

set "SCRIPT_DIR=%~dp0"
set "LIST=%TEMP%\uftp_list_%RANDOM%.txt"

if "%~1"=="" (
    echo.
    echo   Arrastra archivos o carpetas encima de este icono
    echo   y suelta para subirlos por FTP.
    echo   Credenciales en var\scripts\setEnv.bat
    echo   (FTP_USER, FTP_PWD, FTP_HOST).
    echo.
    pause
    exit /b 0
)

rem Guarda la lista de archivos recibidos (una ruta por linea).
(for %%F in (%*) do echo %%~fF) 1> "%LIST%"

rem Carga las credenciales si aun no estan definidas en el entorno.
if not defined FTP_HOST (
    if exist "%SCRIPT_DIR%..\..\var\scripts\setEnv.bat" (
        call "%SCRIPT_DIR%..\..\var\scripts\setEnv.bat"
    ) else (
        echo   No se encontro setEnv.bat en %SCRIPT_DIR%..\..\var\scripts\
        del "%LIST%" >nul 2>&1
        echo.
        pause
        exit /b 1
    )
)

if not defined FTP_HOST (
    echo   FTP_HOST no esta definido. Verifica var\scripts\setEnv.bat
    del "%LIST%" >nul 2>&1
    echo.
    pause
    exit /b 1
)

if not exist "%SCRIPT_DIR%upload.ps1" (
    echo   No se encontro upload.ps1 al lado de subir-ftp.bat
    del "%LIST%" >nul 2>&1
    echo.
    pause
    exit /b 1
)

rem Ejecuta la subida. Usa Windows PowerShell y, si no existe, pwsh.
where powershell >nul 2>&1
if %errorlevel%==0 (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%upload.ps1" "%LIST%" "%FTP_HOST%" "%FTP_USER%" "%FTP_PWD%"
) else (
    pwsh  -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%upload.ps1" "%LIST%" "%FTP_HOST%" "%FTP_USER%" "%FTP_PWD%"
)
echo [bat] powershell termino code=%errorlevel%

del "%LIST%" >nul 2>&1
echo.
pause
