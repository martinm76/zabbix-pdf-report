zabbix-dynamic-report-generation
================================

A PHP-based reporting tool that generates dynamic PDF reports from Zabbix
monitoring data. It uses a (heavily patched) fork of the R&OS ezPDF library
to produce the PDFs and the Zabbix API to retrieve host, graph and item data.

Status
------
Actively maintained. Recently modernised for PHP 8.x — see
"PHP 8 Compatibility" below.

Quick Start
-----------
1. Clone the repository.
2. Run `./fixrights.sh` to create and permission the `reports/` and `tmp/`
   directories. By default these do not exist in the repo and need to be
   writable by the webserver.
3. Copy `config.inc.php.dist` to `config.inc.php` and edit it for your
   environment (Zabbix URL, API user, company branding, etc.). The file is
   fairly well documented internally.
4. Point your webserver at the project directory and open it in a browser.

If `fixrights.sh` doesn't work for your distro, the manual equivalent is:

    mkdir tmp reports
    chmod 777 tmp reports

Patches to make `fixrights.sh` more portable are welcome.

Requirements
------------
- **PHP 8.0 or newer** (tested on PHP 8.1 and 8.2; legacy PHP 7.4 may still
  work but is no longer the supported target — see "PHP 8 Compatibility").
- A reachable Zabbix server with API access. Tested against Zabbix 5.x and
  6.x; 7.x support is in progress (see "Known Issues").
- The following PHP extensions:
    - `php-curl`
    - `php-json` (built in on PHP 8+, but some distros still package it
      separately)
    - `php-mbstring`
    - `php-gd` (used for image handling in graphs)
    - `php-xml`

  On distros where extensions are versioned, prefix the PHP version, e.g.:

      php8.2-curl  php8.2-mbstring  php8.2-gd  php8.2-xml

- A web server (Apache, nginx + php-fpm, etc.) configured to execute PHP
  in this directory.

Composer is **not** required at this time. A future release may switch to
Composer-managed dependencies; that change will be flagged in the changelog.

SELinux
-------
`fixrights.sh` includes some preparation for systems with SELinux enabled,
but it may not be sufficient on all distributions. If PDF generation fails
on a system with SELinux active (RHEL, CentOS, Rocky, Alma, Oracle Linux,
Fedora, etc.), try temporarily setting it to permissive mode to confirm
SELinux is the cause:

    setenforce 0
    sestatus

If reports generate with SELinux permissive but not enforcing, you'll need
to add the appropriate file contexts and/or booleans for your setup.
Patches improving SELinux support are welcome.

New Users
---------
1. Copy `config.inc.php.dist` to `config.inc.php`.
2. Edit `config.inc.php` to match your company name, Zabbix server URL,
   API credentials, report output paths, etc. The shipped `.dist` file
   contains dummy values that will not work as-is.
3. Create `tmp/` and `reports/` directories (or run `./fixrights.sh`).
4. Open the report frontend in your browser.

Existing Users
--------------
After pulling a new version:

1. Diff `config.inc.php.dist` against your local `config.inc.php` and port
   over any new keys or changed defaults.
2. Re-run `./fixrights.sh` if directory structure or permissions have
   changed.
3. If you are upgrading from a pre-PHP-8 deployment, see below.

Upgrading from PHP 7.x to PHP 8.x
---------------------------------
If you have an older deployment running on PHP 7.x and are now moving to
PHP 8.x:

- The bundled R&OS ezPDF library has been patched extensively for PHP 8
  compatibility (PHP 4-style constructors, deprecated `each()`, implicit
  array creation, dynamic-property warnings, and a long-standing recursion
  bomb in the constructor chain). **Do not** replace `inc/class.pdf.php`,
  `inc/class.ezpdf.php`, or `inc/pdf.functions.php` with copies from an
  upstream R&OS distribution — they will not work on PHP 8 and will
  reintroduce bugs we've already fixed.
- Class properties have been declared explicitly where required by PHP
  8.2's deprecation of dynamic properties.
- If you have local modifications to the PDF classes, merge them carefully
  on top of the new versions rather than overwriting.

PHP 8 Compatibility
-------------------
The R&OS ezPDF library shipped with this project was originally written
in the PHP 4 era and used several idioms that no longer work on modern
PHP:

- **PHP 4-style constructors** (a method named the same as its class)
  are no longer auto-promoted to constructors in PHP 8. All affected
  classes (`Cpdf`, `Cezpdf`, `Creport`) now have an explicit
  `__construct()` method, with the legacy class-named method retained
  as an alias for any code that calls it explicitly. Both bodies call
  `parent::__construct()` directly to avoid dynamic-dispatch recursion.
- `each()` and other removed functions have been replaced with their
  modern equivalents (`foreach`, `array_keys()`, etc.).
- Implicit creation of arrays from `null` (now an error in PHP 8.1+)
  has been replaced with explicit initialisation.
- Property declarations have been added to silence PHP 8.2's dynamic
  property deprecation warning.
- Cross-version-fragile constructor chains in the PDF library that used
  `$this->ParentClassName(...)` to "chain up" have been rewritten to
  use `parent::__construct(...)` directly. Mixing the two on PHP 8
  caused infinite recursion via dynamic method dispatch.

If you encounter a PHP 8 deprecation or fatal that we've missed, please
file an issue with the full error trace.

Known Issues
------------
- **Graphs and Zabbix 5.4+ time format**: newer Zabbix versions reject
  numeric Unix timestamps for the `from` / `to` parameters on graph URLs
  and require relative time strings (`now-1d`, `now`) or `YYYY-MM-DD
  HH:MM:SS` absolute datetimes. Reports against newer Zabbix versions may
  show "Field 'from' is not correct: a time range is expected." in place
  of graphs. A fix is in progress.
- **Zabbix 7.x**: not yet fully tested. API authentication and graph
  endpoints may need adjustment.
- **fixrights.sh**: SELinux handling is best-effort and not exhaustive.

Discussion / History
--------------------
Original discussion thread (very old, but useful for historical context):
https://www.zabbix.com/forum/showthread.php?t=24998

Contributing
------------
Patches, bug reports and pull requests are welcome — particularly for:

- SELinux integration
- Newer Zabbix API versions (6.x, 7.x)
- Replacing the bundled ezPDF with a maintained PDF library (e.g. TCPDF,
  mPDF) — likely as a Composer-managed dependency in a future release.
- Cleaner separation of report templates from rendering code.

License
-------
See `LICENSE` (or the file headers in `inc/class.pdf.php` for the R&OS
ezPDF licence terms, which apply to the bundled PDF code).

