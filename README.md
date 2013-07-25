# Cm_RedisSession #

### A Redis-based session handler for Magento with optimistic locking. ###

#### Features: ####
- Falls back to mysql handler if it can't connect to redis. Mysql handler falls back to file handler.
- When a session's data exceeds the compression threshold the session data will be compressed.
- Compression libraries supported are 'gzip', 'lzf' and 'snappy'. Lzf and Snappy are much faster than gzip.
- Compression can be enabled, disabled, or reconfigured on the fly with no loss of session data.
- Expiration is handled by Redis. No garbage collection needed.
- Logs when sessions are not written due to not having or losing their lock.
- Limits the number of concurrent lock requests before a 503 error is returned.
- Detects inactive waiting processes to prevent false-positives in concurrency throttling.
- Detects crashed processes to prevent session deadlocks (Linux only).
- Gives shorter session lifetimes to bots and crawlers to reduce wasted resources.

#### Locking Algorithm Properties: ####
- Only one process may get a write lock on a session.
- A process may lose it's lock if another process breaks it, in which case the session will not be written.
- The lock may be broken after BREAK_AFTER seconds and the process that gets the lock is indeterminate.
- Only MAX_CONCURRENCY processes may be waiting for a lock for the same session or else a 503 error is returned.

## Installation ##

1. Install module using [modman](https://github.com/colinmollenhour/modman):

        modman clone git://github.com/colinmollenhour/Cm_RedisSession.git

2. Configure via app/etc/local.xml adding a `global/redis_session` section with the appropriate configuration if needed.
   See the "Configuration Example" below.
3. Refresh the config cache to allow the module to be installed by Magento.
4. Test the configuration by running the migrateSessions.php script in `--test` mode.

        sudo php .modman/Cm_RedisSession/migrateSessions.php --test

5. Change the `global/session_save` configuration to "db" in app/etc/local.xml. The "db" value is the MySQL handler,
   but Cm_RedisSession overrides it to avoid modifying core files.
6. Migrate the old sessions to Redis. See the "Migration" section below for details. The migration script will clear
   the config cache after migration is complete to activate the config change made in step 5.


#### Configuration Example ####
```xml
<config>
    <global>
        ...
        <session_save>db</session_save>
        <redis_session>                       <!-- All options seen here are the defaults -->
            <host>127.0.0.1</host>            <!-- Specify an absolute path if using a unix socket -->
            <port>6379</port>
            <password></password>             <!-- Specify if your Redis server requires authentication -->
            <timeout>2.5</timeout>            <!-- This is the Redis connection timeout, not the locking timeout -->
            <persistent></persistent>         <!-- Specify unique string to enable persistent connections. E.g.: sess-db0 -->
            <db>0</db>
            <compression_threshold>2048</compression_threshold>  <!-- Set to 0 to disable compression -->
            <compression_lib>gzip</compression_lib>              <!-- gzip, lzf or snappy -->
            <log_level>1</log_level>               <!-- 0 (emergency: system is unusable), 4 (warning; additional information, recommended), 5 (notice: normal but significant condition), 6 (info: informational messages), 7 (debug: the most information for development/testing) -->
            <max_concurrency>6</max_concurrency>                 <!-- maximum number of processes that can wait for a lock on one session; for large production clusters, set this to at least 10% of the number of PHP processes -->
            <break_after_frontend>5</break_after_frontend>       <!-- seconds to wait for a session lock in the frontend; not as critical as admin -->
            <break_after_adminhtml>30</break_after_adminhtml>
            <bot_lifetime>7200</bot_lifetime>                    <!-- Bots get shorter session lifetimes. 0 to disable -->
        </redis_session>
        ...
    </global>
    ...
</config>
```

## Migration ##

A script is included to make session migration from files storage to Redis with minimal downtime very easy.
Use a shell script like this for step 6 of the "Installation" section.

```
cd /var/www              # Magento installation root
touch maintenance.flag   # Enter maintenance mode
sleep 2                  # Allow any running processes to complete
# This will copy sessions into redis and clear the config cache so local.xml changes will take effect
sudo php .modman/Cm_RedisSession/migrateSessions.php -y
rm maintenance.flag      # All done, exit maintenance mode
```

Depending on your server setup this may require some changes. Old sessions are not deleted so you can run it again
if there are problems. The migrateSessions.php script has a `--test` mode which you definitely should use _before_
the final migration. Also, the `--test` mode can be used to compare compression performance and ratios. Last but
not least, the `--test` mode will tell you roughly how much space your compressed sessions will consume so you know
roughly how to configure `maxmemory` if needed. All sessions have an expiration so `volatile-lru` or `allkeys-lru`
are both good `maxmemory-policy` settings.

## Compression ##

Session data compresses very well so using compression is a great way to increase your capacity without
dedicating a ton of RAM to Redis. Compression can be disabled by setting the `compression_threshold` to 0.
The default `compression threshold` is 2048 bytes so any session data equal to or larger than this size
will be compressed with the chosen `compression_lib` which is 'gzip' by default. However, both lzf and
snappy offer much faster compression with comparable compression ratios so I definitely recommend using
one of these if you have root. lzf is easy to install via pecl:

    sudo pecl install lzf

_NOTE:_ If using suhosin with session data encryption enabled (default is suhosin.session.encrypt = on), two things:

1. You will probably get very poor compression ratios.
2. Lzf fails to compress the encrypted data in my experience. No idea why..

If any compression lib fails to compress the session data an error will be logged in system.log and the
session will still be saved without compression. If you have suhosin.session.encrypt on I would either
recommend disabling it (unless you are on a shared host since Magento does it's own session validation already)
or disable compression or at least don't use lzf with encryption enabled.

## Using with [Cm_Cache_Backend_Redis](https://github.com/colinmollenhour/Cm_Cache_Backend_Redis) ##

Using Cm_RedisSession alongside Cm_Cache_Backend_Redis should be no problem at all. The main thing to
keep in mind is that if both the cache and the sessions are using the same database, flushing the cache
backend would also flush the sessions! So, don't use the same 'db' number for both if running only one
instance of Redis. However, using a separate Redis instance for each is recommended to make sure that
one or the other can't run wild consuming space and cause evictions for the other. For example,
configure two instances each with 100M maxmemory rather than one instance with 200M maxmemory.
