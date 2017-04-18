#!/usr/bin/python
# coding: utf-8

# -----------------------------------------------------------------------------
# Import
import sys
import commands
import re
from optparse import OptionParser

# -----------------------------------------------------------------------------
# Variables
exit = 0
output = ""

#------------------------------------------------------------------------------
# Code
parser = OptionParser(usage="%prog [-A file] [-H host]", version="%prog 1.0.0")
parser.add_option("-A", "--authentication-file", dest="auth_file",
                  help="Get the credentials from a file", metavar="AUTH")
parser.add_option("-H", "--hostname", dest="hostname",
                  help="Hostname to perform WMI request", metavar="HOST")
parser.add_option("-V", "--verbose",
                  action="store_true", dest="verbose", default=False,
                  help="don't print status messages to stdout")

(options, args) = parser.parse_args()

# mandatory arguments
if not options.hostname:
    parser.error('Hostname not given')
if not options.auth_file:
    parser.error('Invalide credentials given')

# wmi request against host
results = commands.getstatusoutput('wmic -A ' + options.auth_file + ' --namespace="root\MicrosoftActiveDirectory" //' + \
        options.hostname + \
        ' "select SourceDsaCN, NamingContextDN, LastSyncResult, NumConsecutiveSyncFailures from MSAD_ReplNeighbor" | tail -n +3')

results = results[1].split('\n')

if options.verbose:
        print results

# Get only partition name with regexp
regex = r"^([A-Z]{2}=)(.*?(?=,))"


nbServ = len(results)/5

# Each partition
for y in xrange(0,5):
        status = 0
        # Each server
        for x in xrange(0,nbServ):
                status += int(results[y+(nbServ * x)].split('|')[0])
                if options.verbose:
                    print results[x+(nbServ * y)]
        
        # all replications with success     
        if status == 0:
                matches = re.search(regex, (results[y+(nbServ * x)].split('|')[1]))
                output += matches.group(2) + " success|"

        exit += status

# Exit
if exit == 0:
        print "OK"
        sys.exit(0)
else:
        print "ERROR"
        print output
        sys.exit(2)
