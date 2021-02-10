<!doctype html>
<html lang="ja" xmlns="http://www.w3.org/1999/xhtml">
  <head>

    {$load('php',".data/preload")}
    {$load('php',".data/blog_theme")}
    {$ut->expand('head')}
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    {$load('css',"{$params['data_url']}skeleton/skeleton-onethird",'inline')}
    {$load('css',"{$config['site_url']}css/onethird.8",'inline')}
    {$load('less',"{$params['data_url']}theme",'inline')}
    {$ut->expand('css',1)}
    {$call('blog_css')}
  </head>

  <body >

    <div id='navbar'>
      <div class="container">
      {$call('get_gnav')}
      </div>
    </div>

    <div class=" jumbotron">
      <div class="container">
      {$call('jumbotron')}
      </div>
    </div>

    <!-- article top --> 
    <div class="container">
      <div class="row">
        <div class="nine columns blog_article">
          {$call('breadcrumb')}
          {$ut->expand('article')}
        </div>
        <div class="three columns blog_side">
          {$div('name:blog_side_top','list:true')}
        </div>
      </div>
    </div>
    <!-- article bottom --> 

    <div class="footer">
      <div class="container">
        {$edit('name:footer','mode:inline')}
      </div>
      <div class='copyright'>{$ut->expand('footer')}</div>
    </div>

    {$load('jquery')}
    {$ut->expand_sorted('js',1)}
    {$call('system_toolbar')}
    {$ut->expand('meta')}
    {$call('analytics')}
  </body>
</html>

