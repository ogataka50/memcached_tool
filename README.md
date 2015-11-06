# memcached_tool

## memcached_manage.php

- phpでmemcached-tool相当のスクリプト
- シリアライズにigbinaryを使ったことにより、単にtelnet等でgetしても中身がわからないので、、、

#### usage

```sh
  ex : php memcached_manage.php host port operation
　host : [xxx.xxx.xx.xx]
　port : [11211|/tmp/memcache.sock]
　operation
    stats   : show memcached-stats 
    stats_slabs     : shows slabs info
    dump    : dumps keys and values, (option) $target_slab $dump_num(default is 10)
    get_keys        : get keys, (option) $target_slab $dump_num(default is 10)
    get_item        : get item from key, (option) $key

ex :
php memcached_manage.php localhost 11211 stats
php memcached_manage.php localhost 11211 dump
php memcached_manage.php localhost 11211 dump 1 30
 ※slab1から30件dump
php memcached_manage.php localhost 11211 get_item user_card_protection_123456789
```
