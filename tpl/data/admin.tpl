<!doctype html>
<html lang="ja" xmlns="http://www.w3.org/1999/xhtml">
  <head>

    {$load('php',".data/preload")}
    {$ut->expand('head')}
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    {$load('css',"{$params['data_url']}skeleton/skeleton-onethird",'inline')}
    {$load('css',"{$config['site_url']}css/onethird.8",'inline')}
    {$load('css',"{$params['data_url']}admin")}
    {$ut->expand('css',1)}
  </head>

  <body >

    <div style='min-height:100px;'>
    </div>

    <div id='navbar'>
      <div class="container">
      {$call('get_gnav')}
      </div>
    </div>

    <div class="container">
        {$call('breadcrumb')}
        {$ut->expand('article')}
    </div>

    <div class="footer">
      <div class="container">
        {$ut->expand('footer')}
      </div>
    </div>

    {$load('jquery')}
    {$ut->expand_sorted('js',1)}
    {$call('system_toolbar')}
    {$ut->expand('meta')}
  </body>
</html>

