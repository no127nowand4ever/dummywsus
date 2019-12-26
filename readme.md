Dummy WSUS
----------

### Description
Dummy WSUS is a simple implementation of WSUS server written in PHP. For example
it can be used as dummy server to temporarily prevent Windows 10 from updating
itself while retaining access to the Microsoft Store.

### Usage
#### Using preconfigured server
If you want to use this project locally you can download preconfigured PHP
development server from the releases page. After running the server, you need to
configure Group Policy on your computer to be able to use this service. See
**Configuring Group Policy** for details.

#### Using on a web server
This project should work with any HTTP server with PHP support (PHP with curl
extension required). To install, it simply rename `dummywsus.php` to `index.php`
and drop to any directory on your web server. You also need `wuident.cab` from
genuine WSUS server in the same directory. You can copy this file from `src`
directory of preconfigured server. After doing all of this, you need to
configure Group Policy on your computer to be able to use this service. See
**Configuring Group Policy** for details.

### Configuring Group Policy
To access this WSUS service you need to configure Group Policy on your computer.
Before doing this you need a URL that is required to configure the service.
If you run preconfigured server on the same machine you want to use the service,
the URL will be `http://127.0.0.1:8530/?`. If you run the service on your own
server, the URL will be `http://your-server-address/directory/?`. Simply the URL
is where you put the renamed `dummywsus.php` file with `?` appended.

To configure Group Policy:
1. Run Group Policy Editor by entering `gpedit.msc` in search bar or Run
   dialog.

2. Navigate to *Computer Configuration* > *Administrative Templates* >
   *Windows Compontents* > *Windows Update*

3. Double click *Specify intranet Microsoft update service location*

4. Click *Enabled* in the window that was opened

5. Enter URL of the service in both *Set the intranet update service for
   detecting updates* and *Set the intranet statistics server*

6. Click *OK* and close the Group Policy Editor.

### Windows Update Client compatibility
This project was proven to work with the following Windows versions:
* Windows Vista
* Windows 7
* Windows 8.1
* Windows 10

Windows versions older than Windows 10 require `wuident.cab` to be present in
the same directory as the main PHP file of this project to work.

### License
This project is licensed under the MIT License. See `LICENSE` for details.
