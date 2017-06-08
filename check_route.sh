#!/bin/sh
# Description :
# This Plugin  checks a specific number of jump, in a traceroute command.
# You can define one primary Route(Expected:OK status) and a Secondary
# Route(Fallback: Warning status). If this number of Jump doesn't match any
# of the above, it goes to critical status.
#
# License : GPL
#
#  by   Karl Alexander Monzon.
#  fixed by  Nicolas Le Gall
#
# Requirements :
# Traceroute command and permisions.
#
# Version 1.0 : 07/01/2007
# Initial release.
#
# Version 1.1 : 14/04/2017
# Remove useless code
# Reformat switch case
# Change debug output
#
################################################################################

PROGNAME="check_route"
PROGPATH=`echo $0 | sed -e 's,[\\/][^\\/][^\\/]*$,,'`
REVISION=`echo '$Revision: 1.0 $' | sed -e 's/[^0-9.]//g'`

#
# Initalize a few variables
#
HOSTADDRESS=$1
NJUMP=$2
ROUTE1=$3
ROUTE2=$4
DEBUG=$5

OUTPUT=''
EXITCODE=3
ERRORMSG="There was a problem."

print_help() {
    echo "Nagios Plugin:   $PROGNAME $REVISION"
    echo "by Karl Alexander Monzon"
    echo "fixed by Nicolas Le Gall"
    echo ""
    
    echo "Usage: $PROGNAME"
    echo "check_route [host ip] [jump] [route 1] [route 2] <debug>"
    echo " "
    echo "Options:"
    echo "[host]: The name or IP address of the server to check."
    echo "[jump]: The number of jump to evaluate."
    echo "[route 1]: The IP address of the primary node to check."
    echo "[route 2]: The IP address of the Secundary node to check."
    echo "<debug> [0|1]: optional Parameter. Shows the command for debuging."
    echo " "

    echo "Example:"
    echo "check_route 10.160.254.1 2 192.168.1.1 10.160.2.1"

    exit 0
}


case "$1" in
    --help | -h)
        print_help
        exit 0
        ;;
    --version)
        print_revision $PROGNAME $REVISION
        exit 0
        ;;
    -V)
        print_revision $PROGNAME $REVISION
        exit 0
        ;;
    *)
        if [ "$#" -lt 4 ]; then
            echo "This plugin requires 4 arguments."
            exit "4"
        fi

        if [ "$#" ==  4 ]; then
            DEBUG=0
        fi

        JUMP=`expr $NJUMP + 1`
        RESULT=`traceroute  -n $HOSTADDRESS | head -n $JUMP | tail -n 1`

        if [ $DEBUG == 1 ]; then
            echo "command : traceroute -n $HOSTADDRESS | head -n $JUMP | tail -n 1"
        fi

        SALTO=`echo $RESULT | cut -f 2 -d " "`

        if [ $DEBUG == 1 ]; then
            echo "find : $SALTO"
        fi

        # Check for the error message in the error file.

        SUCCESS=`echo $RESULT | wc -l`

        if [ $SUCCESS == 0 ]; then
            OUTPUT="Error connecting to server running on $HOSTNAME."
            EXITCODE=2
        else
            if [ $SALTO == $ROUTE1 ]; then
                OUTPUT="TRACE OK: Primary Route $SALTO"
                EXITCODE=0
            else
                if [ $SALTO == $ROUTE2 ]; then
                    OUTPUT="TRACE Warning: Secondary Route $SALTO"
                    EXITCODE=1
                else
                    OUTPUT="TRACE Critical : Current Route to: $HOSTADDRESS via: $SALTO"
                    EXITCODE=2
                fi
            fi
        fi

        # Cleaning up.
        echo $OUTPUT
        exit $EXITCODE
        ;;
esac