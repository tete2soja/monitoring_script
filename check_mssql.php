#!/usr/bin/php
<?php

############################################################################
#
# check_mssql - Checks various aspect of MSSQL servers
#
# Version 0.6.6, Copyright (c) 2008 Gary Danko <gdanko@gmail.com>
# Version 0.7.1                2013 Nicholas Scott <nscott@nagios.com>
# Notes:
#
#   Version 0.1.0 - 2008/08/14
#   Initial release. Accepts hostname, username, password, port,
#   database name, and an optional query to run.
#
#   Version 0.2.0 - 2008/08/15
#   You can now execute a query or stored procedure and report
#   on expected results. Queries should be simplistic since
#   only the first row is returned.
#
#   Version 0.2.2 - 2008/08/18
#   Nothing major. Just a couple of cosmetic fixes.
#
#   Version 0.5.0 - 2008/09/29
#   Major rewrite. No new functionality. RegEx added to
#   validate command line options.
#
#   Version 0.6.0 - 2008/10/23
#   Allows the user to specify a SQL file with --query
#
#   Version 0.6.3 - 2008/10/26
#   Removed the -r requirement with -q.
#
#   Version 0.6.4 - 2008/10/31
#   Fixed a bug that would nullify an expected result of "0"
#
#   Version 0.6.5 - 2008/10/31
#   Minor fix for better display of error output.
#
#   Version 0.6.6 - 2008/10/31
#   Prepends "exec " to --storedproc if it doesn't exist.
#
#   Version 0.6.7 - 2012/07/05
#   Enabled instances to be used
#
#   Version 0.6.8 - 2012/08/30
#   Enabled returning of perfdata
#   Warning and crits may be decimal values
#
#   Version 0.6.9 - 2013/01/03
#   Fixed minor exit code bug
#
#   Version 0.7.0 - 2013/04/16
#   Added ability to make ranges on query results
#
#   Version 0.7.1 - 2013/06/17
#   Fixed bug with query ranges
#
#   Version 0.7.2 - 2014/11/20
#   Fixed to comply with Nagios threshold guidelines
#
#   Version 0.7.3 - 2015/02/11
#   Patch from D.Berger
#   1. the warning/critical defaults weren't as documented,
#   2. the warning and critical variables would be referenced undefined if not provided on the command line
#   3. the output wouldn't (always) include OK/WARNING/CRITICAL due to bad logic
#   4. the query result perf data wouldn't include warning/critical thresholds
#
#   Version 0.7.4 - 2015/08/15
#   - Allow usernames with a '_'
#   - Enhanced output_msg
#   - Bug fix for database connection test
#
#   Version 0.7.5 - 2015/09/04
#   - Added support for multiple line output with a second query
#
#   Version 0.7.6 - 2015/09/07
#   - Added support for encoding --encode a query
#
#   Version 0.7.7 - 2015/09/10
#   - Bug fixes
#
#   Version 0.7.8 - 2015/10/05
#   - Added the option read parameter from a php file
#
#   Version 0.7.9 - 2016/05/26
#   - Changed from deprecated mssql functions to PDO
#
#   This plugin will check the general health of an MSSQL
#   server. It will also report the query duration and allows
#   you to set warning and critical thresholds based on the
#   duration.
#
#   Requires:
#       yphp_cli-5.2.5_1 *
#       yphp_mssql-5.2.5_1 *
#       freetds *
#
# License Information:
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
#
############################################################################

$progname = "check_mssql";
$version = "0.7.9";
$warning = "";
$critical = "";
$output_msg = "";
$longquery = "";
$long_output = "";

// Parse the command line options
for ($i = 1; $i < $_SERVER['argc']; $i++) {
    $arg = $_SERVER["argv"][$i];
    switch($arg) {
        case '-h':
        case '--help':
            help();
        break;

        case '-V':
        case '--version':
            version();
        break;

        case '-I':
        case '--instance':
            $db_inst = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-H':
        case '--hostname':
            $db_host = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-u':
        case '-U':
        case '--username':
            $db_user = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-P':
        case '--password':
            $db_pass = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-F':
        case '--cfgfile':
            $db_cfgfile = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-p':
        case '--port':
            $db_port = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-d':
        case '--database':
            $db_name = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '--decode':
            $decode = true;
        break;

        case '--decodeonly':
            $decodeonly = true;
        break;

        case '--encode':
            $encode = true;
        break;

        case '-q':
        case '--query':
            $query = check_command_line_option($_SERVER["argv"][$i], $i);
            $querytype = "query";
        break;

        case '-l':
        case '--longquery':
            $longquery = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-s':
        case '--storedproc':
            $storedproc = check_command_line_option($_SERVER["argv"][$i], $i);
            $querytype = "stored procedure";
        break;

        case '-r':
        case '--result':
            $expected_result = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-W':
        case '--querywarning':
            $query_warning = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-C':
        case '--querycritical':
            $query_critical = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-w':
        case '--warning':
            $warning = check_command_line_option($_SERVER["argv"][$i], $i);
        break;

        case '-c':
        case '--critical':
            $critical = check_command_line_option($_SERVER["argv"][$i], $i);
        break;
    }
}

// Error out if mssql support is not present.
if (!extension_loaded('pdo_dblib')) {
    print "UNKNOWN: MSSQL/DBLIB support is not installed on this server. pdo_dblib must be usable.\n";
    exit(3);
}

// If no options are set, display the help
if ($_SERVER['argc'] == 1) { 
    print "$progname: Could not parse arguments\n"; 
    usage(); 
    exit; 
}

// Read parameters like username and password from a file
if (isset($db_cfgfile)) {
    if (file_exists($db_cfgfile)) {
        include "$db_cfgfile";
    } else {
        print "UNKNOWN: $db_cfgfile does not exist.\n";
        exit(3);
    }
}

// Determine if the query is a SQL file or a text query
if (isset($query)) {
    if (file_exists($query)) {
        $query = file_get_contents($query);
    }
}

// Determine if the query is a SQL file or a text query
if (isset($longquery)) {
    if (file_exists($longquery)) {
        $longquery = file_get_contents($longquery);
    }
}

if (isset($query) and isset($decode)) {
    $query = urldecode($query);
}

if (isset($decodeonly)) {
    if (!isset($query)) {
        print "The --decodeonly switch requires a query -q.\n";
        exit(0);
    }
    print urldecode($query) . "\n";
    exit(0);
}

if (isset($encode)) {
    if (!isset($query)) {
        print "The --encode switch requires a query -q.\n";
        exit(0);
    }
    print str_replace('+', '%20', urlencode($query)) . "\n";
    exit(0); 
}

if (isset($longquery) and isset($decode)) {
    $longquery = urldecode($longquery);
}

// Add "exec" to the beginning of the stored proc if it doesnt exist.
if (isset($storedproc)) {
    if (substr($storedproc, 0, 5) != "exec ") {
        $storedproc = "exec $storedproc";
    }
}

// Do not allow both -q and -s
if (isset($query) && isset($storedproc)) {
    print "UNKNOWN: The -q and -s switches are mutually exclusive. You may not select both.\n";
    exit(3);
}

// -r demands -q and -q demands -r
if (isset($expected_result) && !isset($query)) {
    print "UNKNOWN: The -r switch requires the -q switch. Please specify a query.\n";
    exit(3);
}

// Validate the hostname
if (isset($db_host)) {
    if (!preg_match("/^([a-zA-Z0-9-]+[\.])+([a-zA-Z0-9]+)$/", $db_host)) {
        print "UNKNOWN: Invalid characters in the hostname.\n";
        exit(3);
    }
} else {
    print "UNKNOWN: The required hostname field is missing.\n";
    exit(3);
}

// Validate the port
if (isset($db_port)) {
    if (!preg_match("/^([0-9]{4,5})$/", $db_port)) {
        print "UNKNOWN: The port field should be numeric and in the range 1000-65535.\n";
        exit(3);
    }
} else {
    $db_port = 1433;
}

// Validate the username
if (isset($db_user)) {
    if (!preg_match("/^[a-zA-Z0-9-_]{2,32}$/", $db_user)) {
        print "UNKNOWN: Invalid characters in the username.\n";
        exit(3);
    }
} else {
    print "UNKNOWN: You must specify a username for this DB connection.\n";
    exit(3);
}

// Validate the password
if (empty($db_pass)) {
    print "UNKNOWN: You must specify a password for this DB connection.\n";
    exit(3);
}

// Validate the warning threshold
if (!empty($warning) && !preg_match("/^[0-9]\d*(\.\d+)?$/", $warning)) {
    print "UNKNOWN: Invalid warning threshold.\n";
    exit(3);
}

// Validate the critical threshold
if (!empty($critical) && !preg_match("/^[0-9]\d*(\.\d+)?$/", $critical)) {
    print "UNKNOWN: Invalid critical threshold.\n";
    exit(3);
}

// Is warning greater than critical?
if (!empty($warning) && !empty($critical) && $warning > $critical) {
    $exit_code = 3;
    $output_msg = "UNKNOWN: warning value should be lower than critical value.\n";
    display_output($exit_code, $output_msg);
}

// Attempt to connect to the server
$time_start = microtime(true);

// make sure we have a database specified
if (empty($db_name)) {
    $exit_code = 3;
    $output_msg = "UNKNOWN: You must specify a database with the -q or -s switches.\n";
    display_output($exit_code, $output_msg);
}

$db_dsn = "dblib:host={$db_host};dbname={$db_name}";
try {
    $connection = new PDO($db_dsn, $db_user, $db_pass);
} catch (PDOException $e) {
    $exit_code = 2;
    $output_msg = "CRITICAL: Could not connect to $db_connstr as $db_user (Exception: " . $e->getMessage() . ").\n";
    display_output($exit_code, $output_msg);
}

$time_end = microtime(true);
$query_duration = round(($time_end - $time_start), 6);

// Exit now if no query or stored procedure is specified
if (empty($storedproc) && empty($query)) {
    $output_msg = "Connect time=$query_duration seconds.";
    $state = "OK";
    process_results($query_duration, $warning, $critical, $state, $output_msg);
}

$exit_code = 0;
$state = "OK";

// Attempt to execute the query/stored procedure
$time_start = microtime(true);
$pdo_query = $connection->prepare($query);
if (!$pdo_query->execute()) {
    $exit_code = 2;
    $output_msg = "CRITICAL: Could not execute the $querytype.\n";
    display_output($exit_code, $output_msg);
} else {
    $time_end = microtime(true);
    $query_duration = round(($time_end - $time_start), 6);
    $output_msg = "Query duration=$query_duration seconds.";
}

// Run query for multiple line output
if ($longquery) {
    $pdo_longquery = $connection->prepare($longquery);
    if ($pdo_longquery->execute()) {
        $longrows = $pdo_longquery->fetchALL(PDO::FETCH_ASSOC);
        foreach($longrows as $row) {
            foreach ($row as $col => $val) {
                $long_output .= $val . ' ';
            }
            $long_output = rtrim($long_output);
            $long_output .= "\n";
        }
    } else {
        $long_output = "Long Output Query Failed\n";
    }
}

$result_perf_data = null;
if ($querytype == "query" && (isset($expected_result) || isset($query_warning) || isset($query_critical))) {
    $rows = $pdo_query->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row) {
        foreach($row as $col => $val) {
            $query_result = $val;
            $column_name = $col;
            $output_msg .= " Query result=$query_result";
        }
    }

    if ((isset($query_warning) || isset($query_critical))) {
        $result_perf_data .= "'{$column_name}'={$query_result};{$query_warning};{$query_critical}";
    
        if (!empty($warning)) {
            switch (check_nagios_threshold($query_warning, $query_result)) {
                case 3:
                    $exit_code = 3;
		    $state = _("ERROR: In range threshold START:END, START must be less than or equal to END");
                case 1:
		    $state = "WARNING";
		    $exit_code = 1;
		    $output_msg = "Query result $query_result was higher than query warning threshold $query_warning.";
            }
        }
    
        if (!empty($critical)) {
            switch (check_nagios_threshold($query_critical, $query_result)) {
                    case 3:
                $exit_code = 3;
                $state = _("ERROR: In range threshold START:END, START must be less than or equal to END");
                    case 1:
                $state = "CRITICAL";
                $exit_code = 2;
                $output_msg = "Query result $query_result was higher than query critcal threshold $query_critical.";
                }
            }
    } else {
        if ($query_result == $expected_result) {
	    $output_msg = "Query results matched \"$query_result\", query duration=$query_duration seconds.";
        } else {
            $exit_code = 2;
            $output_msg = "CRITICAL: Query expected \"$expected_result\" but got \"$query_result\".";
        }
    }
}
process_results($query_duration, $warning, $critical, $state, $output_msg, $exit_code, $result_perf_data, $long_output);

//-----------//
// Functions //
//-----------//

// Function to validate a command line option
function check_command_line_option($option, $i) {
    // If the option requires an argument but one isn't sent, bail out
    $next_offset = $i + 1;
    if (!isset($_SERVER['argv'][$next_offset]) || substr($_SERVER['argv'][$next_offset], 0, 1) == "-") {
            print "UNKNOWN: The \"$option\" option requires a value.\n";
    exit(3);
    } else {
            ${$option} = $_SERVER['argv'][++$i];
            return ${$option};
    }
}

// Function to process the results
function process_results($query_duration, $warning, $critical, $state, $output_msg, $exit_code = null, $result_perf_data=null, $long_output=null) {
    
    if (!$query_duration) {
        $response['result_code'] = 3;
        $response['output'] = "UNKNOWN: Could not perform query";
    } 
    
    $result_code = 0;
    $result_prefix = "OK:";
    
    if (!empty($warning)) {
        switch (check_nagios_threshold($warning, $query_duration)) {
            case 3:
                $exit_code = 3;
                $state = _("ERROR: In range threshold START:END, START must be less than or equal to END");
            case 1:
                $state = "WARNING";
                $exit_code = 1;
        }
    }
    
    if (!empty($critical)) {
        switch (check_nagios_threshold($critical, $query_duration)) {
            case 3:
                $exit_code = 3;
                $state = _("ERROR: In range threshold START:END, START must be less than or equal to END");
            case 1:
                $state = "CRITICAL";
                $exit_code = 2;
        }
    }

    $statdata = "$state: $output_msg";
    $perfdata = "query_duration={$query_duration}s;{$warning};{$critical}";
    if($result_perf_data !== NULL) {
        $perfdata .= " $result_perf_data";
    }
    $output_msg = "{$statdata}|{$perfdata}\n{$long_output}";
    display_output($exit_code, $output_msg);
}


function check_nagios_threshold($threshold, $value) {
    $inside = ((substr($threshold, 0, 1) == '@') ? true : false);
    $range = str_replace('@','', $threshold);
    $parts = explode(':', $range);
    
    if (count($parts) > 1) {
        $start = $parts[0];
        $end = $parts[1];
    } else {
        $start = 0;
        $end = $range;
    }

    if (substr($start, 0, 1) == "~") {
        $start = -999999999;
    }
    if (empty($end)) {
        $end = 999999999;
    }
    if ($start > $end) {
        return 3;
    }
    if ($inside > 0) {
        if ($start <= $value && $value <= $end) {
            return 1;
        }
    } else {
        if ($value < $start || $end < $value) {
            return 1;
        }
    }

    return 0;
}

// Function to display the output
function display_output($exit_code, $output_msg) {
    print $output_msg;
    exit($exit_code);
}

// Function to display usage information
function usage() {
    global $progname, $version;
    print <<<EOF
Usage: $progname -H <hostname> --username <username> --password <password>
       [--port <port> | --instance <instance>] [--database <database>] 
       [--query <"text">|filename] [--storeproc <"text">] [--result <text>] 
       [--warning <warn time>] [--critical <critical time>] [--help] [--version]
       [--querywarning <integer>] [--querycritical <integer>]

EOF;
}

// Function to display copyright information
function copyright() {
    global $progname, $version;
    print <<<EOF
Copyright (c) 2008 Gary Danko (gdanko@gmail.com)
          2012 Nicholas Scott (nscott@nagios.com)
This plugin checks various aspect of an MSSQL server. It will also
execute queries or stored procedures and return results based on
query execution times and expected query results.

EOF;
}

// Function to display detailed help
function help() {
    global $progname, $version;
    print "$progname, $version\n";
    copyright();
    print <<<EOF

Options:
 -h, --help
    Print detailed help screen.
 -V, --version
    Print version information.
 -H, --hostname
    Hostname of the MSSQL server.
 -U, --username
    Username to use when logging into the MSSQL server.
 -P, --password
    Password to use when logging into the MSSQL server.
 -F, --cfgfile
    Read parameters from a php file, e. g.
    <?php \$db_port=1433; \$db_user='username'; \$db_pass='password'; ?>
 -p, --port
    Optional MSSQL server port. (Default is 1433).
 -I, --instance
    Optional MSSQL Instance
 -d, --database
    Optional DB name to connect to. 
 -q, --query
    Optional query or SQL file to execute against the MSSQL server.
 -l, --longquery
    Optional query or SQL file to execute against the MSSQL server.
    The query is used for multiple line output only. By default
    Nagios will only read the first 4 KB (MAX_PLUGIN_OUTPUT_LENGTH).
 --decode
    Reads the query -q in urlencoded format. Useful if
    special characters are in your query.
 --decodeonly
    Decode the query -q, prints the decoded string and exits.
 --encode
    Encodes the query -q to in urlencoded format and exits.
 -s, --storedproc
    Optional stored procedure to execute against the MSSQL server.
 -r, --result
    Expected result from the specified query, requires -q. The query
    pulls only the first row for comparison, so you should limit
    yourself to small, simple queries.
 -w, --warning
    Warning threshold in seconds on duration of check 
    Accepts decimal values, note however that there must be at least a 
    leading 0. Example, .0023 is not a valid entry, but 0.0023 is.
 -c, --critical
    Critical threshold in seconds on duration of check
    Accepts decimal values, note however that there must be at least a 
    leading 0. Example, .0023 is not a valid entry, but 0.0023 is.
 -W, --querywarning
    Warning threshold for the query IF IT IS NUMERIC, otherwise, this
    will be ignored.
 -C, --querycritical
    Critical threshold for the query IF IT IS NUMERIC, otherwise, this
    will be ignored.

Example: $progname -H myserver -U myuser -P mypass -q /tmp/query.sql -w 2 -c 5
Example: $progname -H myserver -U myuser -P mypass -q "select count(*) from mytable" -r "632" -w 2 -c 5
Send any questions regarding this utility to gdanko@gmail.com or scot0357@gmail.com.

EOF;
    exit(0);
}

// Function to display version information
function version() {
    global $version;
    print <<<EOF
$version

EOF;
    exit(0);
}
?>
