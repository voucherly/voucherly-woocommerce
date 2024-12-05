@echo off
rem This script is used to generate a zip file for deploy to Wordpress 

echo 🏁 Start generate zip for deploy

call composer install

cd ..
if exist _cache_voucherly rmdir /s /q _cache_voucherly
mkdir "_cache_voucherly"
mkdir "_cache_voucherly\voucherly"

xcopy /E /I voucherly-woocommerce "_cache_voucherly\voucherly" /EXCLUDE:voucherly-woocommerce\scripts\list-of-excluded-files.txt
del /q _cache_voucherly\voucherly\.gitignore
del /q _cache_voucherly\voucherly\.DS_Store
cd _cache_voucherly\voucherly

for /r %%i in (*.DS_Store) do del "%%i"

call composer install --no-dev --optimize-autoloader

cd ..
call 7z a -tzip voucherly-woocommerce.zip * -xr!*.DS_Store
move voucherly-woocommerce.zip ..
cd ..
rmdir /s /q _cache_voucherly
echo 🚀 End generate zip for deploy

pause