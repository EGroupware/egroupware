webodfModule.define("egwCollab/ServerFactory", [
    "webodf/editor/backend/pullbox/Server",
    "webodf/editor/backend/pullbox/SessionBackend",
    "webodf/editor/backend/pullbox/SessionList"],
    function (PullBoxServer, PullBoxSessionBackend, PullBoxSessionList) {
        "use strict";

        /**
        * @constructor
        * @implements ServerFactory
        */
        return function egwCollabServerFactory() {
            this.createServer = function (args) {
                var server;
                args = args || {};

                server = new PullBoxServer(args);
                server.getGenesisUrl = function(sid) {
                    return args.genesisUrl;
                };
                return server;
            };
            this.createSessionBackend = function (sid, mid, server) {
                return new PullBoxSessionBackend(sid, mid, server);
            };
            this.createSessionList = function (server) {
                return new PullBoxSessionList(server);
            };
        };
});
