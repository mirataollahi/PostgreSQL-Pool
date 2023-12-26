## Swoole Postgres Proxy Socket




## Start websocket server

- Need to ensure that the libpq library is installed on the system
```php
apt-get install libpq-dev
```

<br>
<br>
<br>


-Add compilation options when compiling Swoole:./configure --enable-swoole-pgsql

```php
php start.php ./configure --enable-swoole-pg-sql
```