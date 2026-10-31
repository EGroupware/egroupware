#!/bin/sh
# Entrypoint of the n8n container of the development environment, see doc/docker/development.
#
# On the very first start it imports the example workflow next to this script. If EGW_N8N_USER,
# EGW_N8N_PASSWORD, EGW_N8N_MAIL_ACCOUNT and EGW_N8N_AI_KEY are set, it also creates the two
# credentials the workflow uses and publishes (activates) it: from then on it polls the mailbox
# every minute without a single click in n8n. This runs only once, so whatever you change in the
# n8n editor survives restarts - see README.md for how to start over.
dir=$(dirname "$0")
marker=/home/node/.n8n/.egroupware-example

if [ ! -f $marker ]; then
	umask 077
	node "$dir/configure.js" workflow > /tmp/workflow.json &&
		n8n import:workflow --input=/tmp/workflow.json &&
		touch $marker

	if [ -f $marker ] && [ -n "$EGW_N8N_USER" ] && [ -n "$EGW_N8N_PASSWORD" ] &&
		[ -n "$EGW_N8N_MAIL_ACCOUNT" ] && [ -n "$EGW_N8N_AI_KEY" ]; then
		node "$dir/configure.js" credentials > /tmp/credentials.json &&
			n8n import:credentials --input=/tmp/credentials.json &&
			n8n publish:workflow --id=egwMailToTracker
	fi
	rm -f /tmp/workflow.json /tmp/credentials.json
fi

# the image's own entrypoint, which also handles /opt/custom-certificates
exec /docker-entrypoint.sh start
