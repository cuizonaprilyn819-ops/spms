# Aiven MySQL and MySQL Workbench Setup

Legacy reference for the existing source database. New installations should follow
[PostgreSQL setup and cutover](postgresql-setup.md).

## 1. Create the Aiven MySQL database

1. Sign in to Aiven.
2. Create a new service.
3. Choose `MySQL`.
4. Choose your cloud provider and region.
5. Name the service, for example `spms-mysql`.
6. Wait until the service status becomes `Running`.

## 2. Get the connection details from Aiven

Open your MySQL service in Aiven and copy these values from the connection panel:

- Host
- Port
- User, usually `avnadmin`
- Password
- Database name, usually `defaultdb` unless you create a new one
- CA certificate

Download the CA certificate if Aiven provides a `ca.pem` file.

## 3. Configure Laravel

Open `.env` and replace the local database settings with your Aiven values:

```env
DB_CONNECTION=mysql
DB_HOST=your-aiven-host.aivencloud.com
DB_PORT=your-aiven-port
DB_DATABASE=defaultdb
DB_USERNAME=avnadmin
DB_PASSWORD=your-aiven-password
MYSQL_ATTR_SSL_CA=C:\Users\acer\Documents\SPMS\storage\app\aiven-ca.pem
```

Save the Aiven CA certificate as:

```text
C:\Users\acer\Documents\SPMS\storage\app\aiven-ca.pem
```

Then run:

```powershell
php artisan config:clear
php artisan migrate
```

## 4. Connect Aiven to MySQL Workbench

1. Open MySQL Workbench.
2. Click the `+` button beside `MySQL Connections`.
3. Set `Connection Name` to `SPMS Aiven`.
4. Set `Hostname` to your Aiven host.
5. Set `Port` to your Aiven port.
6. Set `Username` to `avnadmin`.
7. Click `Store in Vault` and paste the Aiven password.
8. Go to the `SSL` tab.
9. Set `Use SSL` to `Require`.
10. Set `SSL CA File` to the downloaded Aiven `ca.pem`.
11. Click `Test Connection`.
12. If successful, click `OK`.

## 5. Create a separate SPMS database if needed

If you do not want to use `defaultdb`, create the database from Aiven:

1. Open your Aiven for MySQL service.
2. Go to `Databases`.
3. Click `Create database`.
4. Name it `spms`.
5. Click `Add database`.

Then update Laravel:

```env
DB_DATABASE=spms
```

Finally run:

```powershell
php artisan config:clear
php artisan migrate
```

## 6. Run the application

Use two terminals:

```powershell
php artisan serve
```

```powershell
npm run dev
```

Open:

```text
http://127.0.0.1:8000
```
