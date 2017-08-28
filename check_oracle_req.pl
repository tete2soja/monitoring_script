#!/usr/bin/perl -w

use strict;
use Getopt::Long;
use DBI;
use File::Slurp;

$ENV{ORACLE_HOME} = "/usr/lib/oracle/11.2/client64";
$ENV{LD_LIBRARY_PATH} = "/usr/lib/oracle/11.2/client64/lib";

# Nagios specific

use lib "/usr/lib/nagios/plugins";
use utils qw(%ERRORS $TIMEOUT);
#my $TIMEOUT = 15;
#my %ERRORS=('OK'=>0,'WARNING'=>1,'CRITICAL'=>2,'UNKNOWN'=>3,'DEPENDENT'=>4);

my $o_host;
my $o_user="sa";
my $o_pw="";
my $o_inst="";
my $o_inst2="";
my $port="1521";
my $o_req="";
my $o_sid;
my $o_inst;

sub print_usage {
    print "\n";
    print "Usage: check_oracle_req.pl -H <host> -s <SID> -u <username> -p <password> -r <req.sql> [-L]\n";
    print "\n";
    print "\tDefault Username is 'sa' without a password\n\n";
    print "\tScript should be run on the PRINCIPAL with a read-only user\n";
    print "\tIf you want to run it on the MIRROR, the user MUST have SYSADMIN rights on the SQL-Server\n";
    print "\totherwise you get NULL\n";
    print "\tThe request must in a file.\n";
    print "\n";
}

sub check_options {
    Getopt::Long::Configure("bundling");
    GetOptions(
        'H:s'   => \$o_host,
        'u:s'   => \$o_user,
        'p:s'   => \$o_pw,
        'r:s'    => \$o_req,
        's:s'    => \$o_sid,
        'L:s'    => \$o_lock,
        );
    if (!defined ($o_host) || !defined ($o_sid)) { print_usage(); exit $ERRORS{"UNKNOWN"} };
}

########## MAIN #######

check_options();

my $exit_val;

# Connect to database
my @o_inst2 = split /:/, $o_inst;
my $portdef = $o_inst2[1];
if (defined($portdef))
{
        #print "ok";
        $port = $o_inst2[1];
}

my $dbh = DBI->connect("dbi:Oracle:host=$o_host;port=$port;sid=$o_sid","$o_user","$o_pw") or exit $ERRORS{"UNKNOWN"};

my $text = read_file($o_req);

my $sth=$dbh->prepare($text);
$sth->execute;

my $spid;

while (my @row = $sth->fetchrow_array) {
    foreach (@row) {
        $_ = "\t" if !defined($_);
    }
    if (defined($o_lock))
    {
        print "Machine : " . $row["5"] . " - Utilisateur : " . $row["6"] . " - Programme : " . $row["2"] . " - PID : " . $row["3"] . " - SID : " . $row["0"];
        print "\n";
    }
    $spid = $row["1"];
}

if(defined($spid)) {
        $exit_val=$ERRORS{"CRITICAL"};
} else {
        $exit_val=$ERRORS{"OK"};
}

print "OK\n"        if ($exit_val eq $ERRORS{"OK"});
print "CRITICAL\n" if ($exit_val eq $ERRORS{"CRITICAL"});
exit $exit_val;
