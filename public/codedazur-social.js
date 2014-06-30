/**
 * @namespace global
 * @name Social
 * @author Rick Schoo <rick@codedazur.nl> | Code d'Azur
 * @date: 22-03-2013
 */

/*global Social:true, FB, gapi, window, document, Config */

define([
    
    'facebook',
    'twitter'
    
], function () {

    'use strict';

    window.Social = {
        debug: false,

        Event: {
            events: {},

            /**
             * Parses the query string for event triggers, created by for example backend redirects;
             * Call this function when javascript is ready, and when events are set
             */
            parse: function () {
                if (Social.debug) {
                    try {
                        console.info('Social.Event.parse');
                    } catch (e) {}
                }
                if (/__st__=/.test(window.location.search)) {
                    var obj, match = window.location.search.match(/__st__=([^&]+)/);
                    if (match && match[1] && JSON) {
                        try {
                            obj = JSON.parse(decodeURIComponent(match[1]));
                        } catch (e) {
                            try {
                                console.warn('Social.Event.parse: Failed to decode Social trigger event', e);
                            } catch (e) {}
                        }

                        Social.Event.call(obj.type, obj.msg);

                        return true;
                    }
                }

                return false;
            },

            /**
             * @param type {String}
             * @param fn {Function}
             * @param uid [optional]
             * @returns {Social.Event}
             * @static
             */
            add: function (type, fn, uid) {
                if (uid === undefined) {
                    uid = Social.Event.getUid();
                }

                if (Social.debug) {
                    try {
                        console.info('Social.Event.add: ' + type, fn, uid);
                    } catch (e) {}
                }

                if (this.events[type] === undefined) {
                    this.events[type] = {};
                }

                this.events[type][uid] = fn;

                return Social.Event;
            },

            /**
             * @returns {string}
             */
            getUid: function () {
                return Math.random() * Math.pow(10, 20) + '';
            },

            /**
             * @param type
             * @param param
             * @returns {*}
             * @static
             */
            call: function (type, param) {
                if (Social.debug) {
                    try {
                        console.info('Social.Event.call: ' + type);
                    } catch (e) {}
                }

                var uid;

                if (this.events[type] !== undefined) {
                    for (uid in this.events[type]) {
                        if (this.events[type].hasOwnProperty(uid) && typeof this.events[type][uid] === 'function') {
                            this.events[type][uid](param);
                        }
                    }
                }
                return Social.Event;
            },

            /**
             * Remove event type
             * @param type
             */
            remove: function (type) {
                if (this.events[type] !== undefined) {
                    delete this.events[type];
                }
                return Social.Event;
            }
        },

        /**
         * @param url
         * @param title
         * @param width
         * @param height
         * @constructor
         */
        Popup: function (url, title, width, height) {
            try {
                var left = Math.round((screen.width / 2) - (width / 2)),
                    top = Math.round((screen.height / 2) - (height / 2));

                var popup = window.open(url, '', 'scrollbars=yes,resizable=yes,toolbar=no,location=yes,width=' + width + ',height=' + height + ',left=' + left + ',top=' + top);

                if (popup) {
                    popup.focus();
                } else {
                    Social.Event.call('popup.blocked');
                }
            } catch (e) {
                Social.Event.call('popup.blocked');
            }
        }
    };

    window.Social.Facebook = {
        loaded: false,

        STATICS: {
            EVENT: {
                READY: 'facebook.ready', // SDK API is loaded and ready
                LIKE: 'facebook.like', // When a like widget is liked
                UNLIKE: 'facebook.unlike', // When a like widget is unliked
                CONNECT_START: 'facebook.connect.start', // When an attempt to connect is made
                CONNECT_SUCCESS: 'facebook.connect.success', // When a login connection is successful
                CONNECT_FAILURE: 'facebook.connect.failure', // When a login connection failed
                CONNECT_STATUS: 'facebook.connect.status', // Check the status of the current connection
                SHARE_SUCCESS: 'facebook.share.success', // When a SDK share was successful
                SHARE_FAILURE: 'facebook.share.failure', // When a SDK share failed
                DISCONNECTED: 'facebook.disconnected' // When Facebook is disconnected
            },
            // Connect type, which can be set in the options connect object, type variable
            CONNECT_TYPE: {
                BACKEND_REDIRECT: 'backend-redirect', // Login by backend, by redirecting the window
                BACKEND_POPUP: 'backend-popup', // Login by the backend, which redirects in a popup
                SDK_LOGIN: 'sdk-login', // Use the SDK to login with; preferred method
                SDK_UI: 'sdk-ui' // Used for canvas apps and tabs - within - Facebook
            },
            // Share type, which can be set in the options share object, type variable
            SHARE_TYPE: {
                SDK_UI: 'sdk-ui'
            },
            DISPLAY: {
                PAGE: 'page',
                POPUP: 'popup', // Used for connect type SDK_LOGIN and SDK_UI
                IFRAME: 'iframe', // Not valid for login
                ASYNC: 'async' // Not valid for login
            }
        },

        options: {
            connect: {
                scope: undefined, // General permission scope for the login; specifying the scope for each separate connect call is encouraged, so please leave this undefined
                type: 'sdk-login',
                display: 'popup', // Change this option for connect type SDK_LOGIN and SDK_UI, specifies how the display type of the connect
                authenticationUri: 'facebook/social-user-authentication/' // The URL to use connect type BACKEND_REDIRECT and BACKEND_POPUP uses
            },
            share: {
                type: 'sdk-ui',
                display: 'popup'
            },
            locale: 'en_US',
            appId: undefined
        },

        /**
         * Initialize Facebook; loads the API, and adds events
         * @param {string|int} appId
         * @param {function} onReady
         */
        load: function (appId, onReady) {

            if (appId) {
                Social.Facebook.options.appId = appId;
            }

            FB.init({
                appId: Social.Facebook.options.appId,
                status: true,
                cookie: true,
                xfbml: false,
                oauth: true
            });

            Social.Facebook.loaded = true;
            Social.Event.call(Social.Facebook.STATICS.EVENT.READY, FB);

            if (typeof onReady === 'function') {
                onReady.call();
            }

            // Add base events
            FB.Event.subscribe('edge.create', function (response) {
                Social.Event.call(Social.Facebook.STATICS.EVENT.LIKE, response);
            });
            FB.Event.subscribe('edge.remove', function (response) {
                Social.Event.call(Social.Facebook.STATICS.EVENT.UNLIKE, response);
            });
        },

        /**
         * Connect to Facebook
         * @param {string} scope Optional
         */
        connect: function (scope, redirectData) {
            if (scope === undefined) {
                scope = Social.Facebook.options.connect.scope;
            }

            Social.Event
                .call('connect.start', 'facebook')
                .call(Social.Facebook.STATICS.EVENT.CONNECT_START);

            var url, popup;

            switch (Social.Facebook.options.connect.type) {

            case Social.Facebook.STATICS.CONNECT_TYPE.BACKEND_REDIRECT:
                url = '/' + Social.Facebook.options.connect.authenticationUri;
                if (redirectData) {
                    url += '?__rdd__=' + redirectData;
                }
                if (scope !== undefined) {
                    url += (url.indexOf('?') !== -1 ? '&' : '?') + 'scope=' + scope;
                }
                url += (url.indexOf('?') !== -1 ? '&' : '?') + 'type=redirect&sid=' + window.settings.sessionID;
                window.location.href = url;
                break;

            case Social.Facebook.STATICS.CONNECT_TYPE.BACKEND_POPUP:
                url = '/' + Social.Facebook.options.connect.authenticationUri;
                if (scope !== undefined) {
                    url += (url.indexOf('?') !== -1 ? '&' : '?') + 'scope=' + scope;
                }
                url += (url.indexOf('?') !== -1 ? '&' : '?') + 'type=popup&sid=' + window.settings.sessionID;
                if (redirectData) {
                    url += '&__rdd__=' + redirectData;
                }
                popup = new Social.Popup(url, 'Facebook', 640, 330);
                break;

            case Social.Facebook.STATICS.CONNECT_TYPE.SDK_LOGIN:
                //FB.getLoginStatus(function (response) {
                //Social.Event.call(Social.Facebook.STATICS.EVENT.CONNECT_STATUS, response);

                // Already connected
                /* if (response.status === 'connected') {
                     if (Social.debug) {
                     try {
                     console.info('Social.Facebook.connect: already connected');
                     } catch (e) {
                     }
                     }

                     // Check permissions
                     FB.api('/me/permissions', function (response) {
                     console.log(response);
                     });

                     Social.Event
                     .call(Social.Facebook.STATICS.EVENT.CONNECT_SUCCESS, response)
                     .call('connect.success', response);
                     } */

                // Connect
                if (Social.debug) {
                    try {
                        console.info('Social.Facebook.connect: calling FB.login');
                    } catch (e) {}
                }

                FB.getLoginStatus(function (response) {
                    if (response.authResponse) {
                        
                        response.referer = redirectData;
                        
                        Social.Event
                            .call(Social.Facebook.STATICS.EVENT.CONNECT_SUCCESS, response)
                            .call('connect.success', response);
                    } else {
                        FB.login(function (response) {
                            if (response.authResponse) {
                                
                                response.referer = redirectData;
                                
                                Social.Event
                                    .call(Social.Facebook.STATICS.EVENT.CONNECT_SUCCESS, response)
                                    .call('connect.success', response);
                            } else {
                                Social.Event
                                    .call(Social.Facebook.STATICS.EVENT.CONNECT_FAILURE, response)
                                    .call('connect.failure', response);
                            }
                        }, {
                            scope: scope,
                            display: Social.Facebook.options.connect.display
                        });
                    }
                });

                //});
                break;

            case Social.Facebook.STATICS.CONNECT_TYPE.SDK_UI:
            default:
                FB.ui({
                    method: 'oauth',
                    client_id: this.appId,
                    scope: scope !== undefined ? scope : '',
                    response_type: 'code',
                    display: Social.Facebook.options.connect.display
                });
                break;

            }
        },

        /**
         * @param name
         * @param link
         * @param picture
         * @param description
         * @param caption
         * @param userUid
         */
        share: function (name, link, picture, description, caption, userUid) {
            switch (Social.Facebook.options.share.type) {

            case Social.Facebook.STATICS.SHARE_TYPE.POPUP:
            default:
                FB.ui({
                    method: 'feed',
                    name: name,
                    link: link,
                    picture: picture,
                    description: description,
                    caption: caption,
                    display: Social.Facebook.options.share.display,
                    to: userUid
                }, function (response) {
                    if (response && response.post_id) {
                        Social.Event.call(Social.Facebook.STATICS.EVENT.SHARE_SUCCESS, response);
                    } else {
                        Social.Event.call(Social.Facebook.STATICS.EVENT.SHARE_FAILURE, response);
                    }
                });
                break;

            }

        },

        /**
         * Request connection data from facebook
         */
        requestStatus: function () {
            FB.getLoginStatus(function (response) {
                Social.Event.call(Social.Facebook.STATICS.EVENT.CONNECT_STATUS, response.authResponse ? true : false);
            });
        },

        /**
         *
         */
        disconnect: function () {
            FB.logout();
            Social.Event.call(Social.Facebook.STATICS.EVENT.DISCONNECTED);
        }
    };

    window.Social.Twitter = {
        loaded: false,

        STATICS: {
            EVENT: {
                READY: 'twitter.ready', // SDK API is loaded and ready
                CLICK: 'twitter.click', // When the user invokes a 'Web Intent' from within a widget
                TWEET: 'twitter.tweet', // When the user publishes a Tweet (either new, or a reply) through the Tweet widget
                CONNECT_START: 'twitter.connect.start', // When an attempt to connect is made
                CONNECT_SUCCESS: 'twitter.connect.success', // When a login connection is successful
                CONNECT_FAILURE: 'twitter.connect.failure' // When a login connection failed
            },
            CONNECT_TYPE: {
                BACKEND_REDIRECT: 'backend-redirect',
                BACKEND_POPUP: 'backend-popup'
            },
            SHARE_TYPE: {
                POPUP: 'popup' // Non widget share, does not provide feedback
            }
        },

        options: {
            connect: {
                type: 'backend-redirect',
                authenticationUri: 'twitter/social-user-authentication/'
            },
            share: {
                type: 'popup'
            },
            locale: 'en_US'
        },

        /**
         * Initialize Twitter; loads the API, and adds events. Only needed if you want to use widgets and their events.
         * Not needed for Twitter connect.
         */
        load: function () {
            // window.twttr = (function (d, s, id) {
            //     var t, js, fjs = d.getElementsByTagName(s)[0];
            //     if (d.getElementById(id)) return;
            //     js = d.createElement(s);
            //     js.id = id;
            //     js.src = 'https://platform.twitter.com/widgets.js';
            //     fjs.parentNode.insertBefore(js, fjs);
            //     return window.twttr || (t = { _e: [], ready: function (f) {
            //         t._e.push(f);
            //     }});
            // }(document, 'script', 'twitter-wjs'));

            // twttr.ready(function (twttr) {

            Social.Twitter.loaded = true;

            Social.Event.call(Social.Twitter.STATICS.EVENT.READY, twttr);

            twttr.events.bind('click', function (event) {
                Social.Event.call(Social.Twitter.STATICS.EVENT.CLICK, event);
            });
            twttr.events.bind('tweet', function (event) {
                Social.Event
                    .call(Social.Twitter.STATICS.EVENT.TWEET, event)
                    .call('twitter.share.success', event);
            });

            // });
        },

        /**
         * Connect to Twitter
         */
        connect: function (scope, redirectData) {
            Social.Event
                .call('connect.start', 'twitter')
                .call('twitter.connect.start');

            var url, popup;

            switch (Social.Twitter.options.connect.type) {

            case Social.Twitter.STATICS.CONNECT_TYPE.BACKEND_REDIRECT:
                url = '/' + Social.Twitter.options.connect.authenticationUri;
                url += (url.indexOf('?') !== -1 ? '&' : '?') + 'type=redirect&sid=' + window.settings.sessionID;
                if (redirectData) {
                    url += '&__rdd__=' + redirectData;
                }
                window.location.href = url;
                break;

            case Social.Twitter.STATICS.CONNECT_TYPE.BACKEND_POPUP:
                url = '/' + Social.Twitter.options.connect.authenticationUri;
                url += (url.indexOf('?') !== -1 ? '&' : '?') + 'type=popup&sid=' + window.settings.sessionID;
                if (redirectData) {
                    url += '&__rdd__=' + redirectData;
                }
                popup = new Social.Popup(url, 'Twitter', 620, 620);
                break;

            }
        },

        /**
         * @param text
         * @param url
         */
        share: function (text, url) {
            switch (Social.Twitter.options.share.type) {

            case Social.Twitter.STATICS.SHARE_TYPE.POPUP:
            default:
                url = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(url);
                var popup = new Social.Popup(url, 'Twitter share', 550, 346);
                break;

            }
        }
    };

});
