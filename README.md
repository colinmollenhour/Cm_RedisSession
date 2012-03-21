# Cm_RedisSession #

### A Redis-based session handler for Magento with optimistic locking. ###

#### Features: ####
- Falls back to MySQL handler if it can't connect to Redis. MySQL handler falls back to file handler.
- When a session's data exceeds the compression threshold the session data will be compressed.
- Compression libraries supported are 'gzip', 'lzf' and 'snappy'. Lzf and Snappy are much faster than gzip.
- Compression can be enabled, disabled, or reconfigured on the fly with no loss of session data.
- Expiration is handled by Redis. No garbage collection needed.
- Logs when sessions are not written due to not having or losing their lock.

#### Locking Algorithm Properties: ####
- Only one process may get a write lock on a session
- A process may lose it's write lock if the break attempts are exceeded
- If a process cannot get a lock on the session or loses it's lock, it will
  read the session but will silently fail to write the session.
- The more processes there are requesting a lock on the session, the faster the lock will be broken.

## Installation ##

1. Install module using [modman](https://github.com/colinmollenhour/modman):

        modman clone git://github.com/colinmollenhour/Cm_RedisSession.git

2. Configure via app/etc/local.xml. There are two steps:

    1. Change `global/session_save` value to "db".
    2. Add a `global/redis_session` section with the appropriate configuration.

```xml
<config>
    <global>
        ...
        <session_save>db</session_save>
        <redis_session>
            <host>127.0.0.1</host>
            <port>6379</port>
            <timeout>2.5</timeout>
            <db>0</db>
            <compression_threshold>2048</compression_threshold>
            <compression_lib>gzip</compression_lib>
        </redis_session>
        ...
    </global>
    ...
</config>
```

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
2. Lzf fails to compress the data in my experience. No idea why..

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
