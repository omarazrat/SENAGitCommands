call setEnv
wget --ftp-user=%FTP_USER% --ftp-password=%FTP_PWD% ftp://%FTP_HOST% -r
REM wget --ftp-user=%FTP_USER% --ftp-password=%FTP_PWD% ftp://%FTP_HOST% -r -P %1
@pause