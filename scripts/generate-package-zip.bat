@echo off
rem This script is used to generate a zip file for deploy to Wordpress 

echo 🏁 Start generate zip for deploy

cd ..
if exist _cache_voucherly rmdir /s /q _cache_voucherly
mkdir "_cache_voucherly"
mkdir "_cache_voucherly\voucherly-woocommerce"

xcopy /E /I voucherly-woocommerce "_cache_voucherly\voucherly-woocommerce" /EXCLUDE:voucherly-woocommerce\scripts\list-of-excluded-files.txt
del /q _cache_voucherly\voucherly-woocommerce\.gitignore
del /q _cache_voucherly\voucherly-woocommerce\.DS_Store
del /q _cache_voucherly\voucherly-woocommerce\.nvmrc
cd _cache_voucherly\voucherly-woocommerce


cd ..
call 7z a -tzip voucherly-woocommerce.zip * -xr!*.DS_Store
move voucherly-woocommerce.zip ..
cd ..
rmdir /s /q _cache_voucherly
echo 🚀 End generate zip for deploy
pause