# Swoole Postgres Proxy Socket

### Overview

The Application class is the main entry point for the Swoole socket server application. It manages the Swoole socket
server, PostgreSQL connection pool, and handles events such as receiving data from client connections.

### Application Class

#### Methods

* `init()` : Initializes the socket server, command line logger, and PostgreSQL connection manager. Also defines Swoole
  socket server events.

* `makeSocketServer()`: Creates a new Swoole socket server with configured host, port, and worker number.

* `run()` : Starts the socket server if it hasn't been started already.

* `onReceive()`: Handles received data from a socket client.

* `onStart()` : Handles the event when the TCP socket server starts.

* `onConnect()` : Handles the event when a new client connects to the TCP socket server.

* `onClose()`: Handles the event when a client connection is closed on the TCP socket server.

<br>
<br>

### Configuration .env File

```php
# Environment Setting
IS_PRODUCTION=false

# Socket Server Configuration
SOCKET_SERVER_HOST=127.0.0.1
SOCKET_SERVER_PORT=8100
SOCKET_WORKER_NUMBER=2

# PostgreSQL Database Configuration
DATABASE_HOST=127.0.0.1
DATABASE_PORT=5432
DATABASE_NAME=search
DATABASE_CHARSET=utf8
DATABASE_USERNAME=postgres
DATABASE_PASSWORD=password
DATABASE_SCHEMA=amir
LINKS_TABLE=links_statics
POSTGRES_POOL_SIZE=16
```

Feel free to update and expand this section based on additional configurations or any specific instructions that users
might need when configuring their environment.
<br>
<br>
<br>

### Run Socket server

```php
composer install

cp .env.example .env

php start.php
```


