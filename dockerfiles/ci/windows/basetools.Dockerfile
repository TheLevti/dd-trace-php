ARG vsVersion
FROM datadog/dd-trace-ci:windows-base-$vsVersion

# I really need some sane file editing utilities
RUN powershell "Invoke-WebRequest https://ftp.nluug.nl/pub/vim/pc/vim90w32.zip -OutFile /tmp/vim90w32.zip; Expand-Archive /tmp/vim90w32.zip /tmp; move C:\tmp\vim\vim90\tee.exe C:\Windows\tee.exe; move C:\tmp\vim\vim90\vim.exe C:\Windows\vim.exe; move C:\tmp\vim\vim90\xxd.exe C:\Windows\xxd.exe; Remove-Item /tmp/vim90w32.zip; Remove-Item -Recurse C:\tmp\vim"

RUN powershell "Invoke-WebRequest https://static.rust-lang.org/rustup/dist/x86_64-pc-windows-msvc/rustup-init.exe -OutFile /tmp/rustup-init.exe; cmd /S /C /tmp/rustup-init.exe --profile minimal -y --default-toolchain=1.71.0; Remove-Item /tmp/rustup-init.exe"

# initial setup

ARG sdkVersion
RUN powershell "cd /tmp; Invoke-WebRequest https://github.com/php/php-sdk-binary-tools/archive/refs/tags/php-sdk-%sdkVersion%.zip -OutFile php-sdk.zip; Expand-Archive php-sdk.zip; move php-sdk\php-sdk-binary-tools-php-sdk-%sdkVersion% /php-sdk; Remove-Item php-sdk; Remove-Item php-sdk.zip"

WORKDIR /php-sdk
