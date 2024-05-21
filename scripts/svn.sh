sh scripts/generate-pot.sh

rm -rf tmp-svn
svn co https://plugins.svn.wordpress.org/woo-voucherly tmp-svn
(cd tmp-svn/trunk && rm -rf *)
cp LICENSE tmp-svn/trunk
cp readme.txt tmp-svn/trunk
cp logo.svg tmp-svn/trunk
cp voucherly.php tmp-svn/trunk
cp woo-voucherly.php tmp-svn/trunk
cp woo-voucherly.pot tmp-svn/trunk
cp -R assets  tmp-svn/trunk
cp -R includes tmp-svn/trunk
cp -R resources tmp-svn/trunk
cp -R voucherly-sdk tmp-svn/trunk


echo "\nnext manual commands:"
echo "  (cd tmp-svn && svn add trunk/**/* && tmp-svn svn stat)"
echo "  (svn ci --username voucherly -m 'My changelog')"
echo "  (svn cp trunk tags/x.x.x)"
echo "  (svn ci --username voucherly -m 'Created tag x.x.x')"
