@extends('template')

@section('content')
    <div id="page-wrapper">
        <div id="dashboard" class="container-fluid">
            <div class="row">
                <div class="col-lg-6">
                    <h2>
                        <i class="fa fa-signal"></i> {{Lang::get('dashboard.activity')}}
                    </h2>
                    <ul class="nav-select-view nav navbar-right top-nav">
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                <i href="#"class="fa fa-fw fa-bars"></i> {{Lang::get('dashboard.selectView')}} <b class="caret"></b>
                            </a>
                            <ul class="dropdown-menu">
                                <li class="active">
                                    <a class="stream"><i href="#" class="fa fa-fw fa-cloud"></i> {{Lang::get('dashboard.liveView')}}</a>
                                </li>
                                <li>
                                    <a class="activity"><i href="#" class="fa fa-fw fa-refresh"></i> {{Lang::get('dashboard.lastActivity')}}</a>
                                </li>
                                <li>
                                    <a class="heat"><i href="#" class="fa fa-fw fa-fire"></i> {{Lang::get('dashboard.heatmap')}}</a>
                                </li>
                            </ul>
                        </li>
                    </ul>

                    <div id="activity-sequence">
                        <ul id="activity-choice">
                            <li class="stream">
                                <div id="livestream">
                                    <div id="livestream_webrtc" style="display: none">
                                        <video id="remoteVideo" width="100%" autoplay=""></video>
                                    </div>
                                </div>
                            </li>
                            <li class="activity">
                                <canvas id="latest-activity-sequence"></canvas>
                            </li>
                            <li class="heat">
                                <div class="heatmap" style="width: 100%;"></div>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6 hide-on-mobile">
                    <h2><i class="fa fa-pie-chart"></i> {{Lang::get('dashboard.overview')}}</h2>
                    <!--<div id="morris-donut"></div>-->
                    <div id="time-donut-wrapper">
                        <canvas id="time-donut"></canvas>
                        <div id="time-donut-legend"></div>
                    </div>
                </div>
                <div class="col-lg-6 hide-on-mobile">
                    <h2><i class="fa fa-clock-o"></i> {{Lang::get('dashboard.hour')}}</h2>
                    <div id="time-graph">
                        <canvas id="time-chart"></canvas>
                    </div>
                </div>
                <div class="col-lg-6 hide-on-mobile">
                    <h2><i class="fa fa-calendar"></i> {{Lang::get('dashboard.weekday')}}</h2>
                    <div id="radar-graph">
                        <canvas id="radar-chart"></canvas>
                    </div>
                </div>
            </div>
            <script type="text/javascript">

                function start() {

                    endpoint = new RTCPeerConnection(pc_config, pcConstraints);
                    jsCmd = {
                        message_type : "start_command",
                    };
                    wsConn.send(JSON.stringify(jsCmd));
                }

                function stop() {
                    jsCmd = {
                        message_type : "stop_command",
                    };
                    wsConn.send(JSON.stringify(jsCmd));
                }
                
                var pc_config = {'iceServers':
                    [
                        {
                            'url': "{{$webrtc_turnserver}}",
                            'credential': "{{$webrtc_turnserverUserName}}",
                            'username': "{{$webrtc_turnserverPassword}}"
                        },
                    ]
                };

                var pcConstraints = {"optional": [{"DtlsSrtpKeyAgreement": true}]};

                var sdpConstraints = {'mandatory': {
                    'OfferToReceiveAudio':true,
                    'OfferToReceiveVideo':true
                }
                };
                
                var endpoint;
                
                var remoteVideo = document.getElementById("remoteVideo");
                
                function remoteVideoReceived(akiOlayi) {
                    remoteVideo.src = URL.createObjectURL(akiOlayi.stream);
                    $("#livestream .load5").hide();
                }
                
                function localConfigurationReceived(configuration)
                {
                    endpoint.setLocalDescription(configuration);
                    
                    jsCmd = {
                        message_type : "configuration",
                        sdp: configuration.sdp,
                        type: configuration.type
                    };

                    wsConn.send(JSON.stringify(jsCmd));
                }
                
                function localIceCandidateReceived(event) {
                    if (event.candidate) {
                        /*ice adayi alınınca sunucuya ve onun üzerinden diğer uçbirime
                         gönderiyoruz.*/


                        jsCmd = {
                            message_type : "candidate",
                            label: event.candidate.sdpMLineIndex,
                            id: event.candidate.sdpMid,
                            candidate: event.candidate.candidate
                        };

                        wsConn.send(JSON.stringify(jsCmd));
                    }
                }
                var wsConn;
                function startWebSocket() {
                    wsConn = new WebSocket("{{$webrtc_signallingUrl}}");

                    wsConn.onopen = function() {

                        var jsCmd = {
                            user_type : "watcher",
                        };

                        wsConn.send(JSON.stringify(jsCmd));
                        console.log("websocket connected");
                    }

                    wsConn.onmessage = function(event) {
                        obj = JSON.parse(event.data);
                        console.log(event.data);
                        if (obj.message_type == "configuration") {

                            console.log("remote configuration received...");

                            endpoint.onicecandidate = localIceCandidateReceived;
                            endpoint.onaddstream = remoteVideoReceived;

                            console.log(obj.sdp);
                            endpoint.setRemoteDescription(
                                new RTCSessionDescription({
                                    type: obj.type,
                                    sdp:obj.sdp
                                }));
                            
                            console.log("remote sdp: ");
                            console.log(obj.sdp);
                            endpoint.createAnswer(localConfigurationReceived,
                                function(){console.log("answer failure");}, sdpConstraints);

                        }
                        else if (obj.message_type == "publisher_ready") {
                            start();
                        }
                        else if (obj.message_type == "candidate") {
                            console.log("adding ice candidate");
                            endpoint.addIceCandidate(new RTCIceCandidate({
                                sdpMid: obj.sdpMid,
                                sdpMLineIndex: obj.sdpMLineIndex,
                                candidate:obj.candidate
                            }));
                        }

                    }

                    wsConn.onerror = function(hata) {
                        console.log(" error occured: " + hata);
                    }

                    wsConn.onclose = function(event) {
                        console.log("connection closed.");
                    }

                }

            </script>
            <script type="text/javascript">

                // Render dashboard sequencer (latest sequence)
                require([_jsBase + 'main.js'], function(common)
                {
                    require(["jquery"], function($)
                    {
                        // on page load, hide all except live stream
                        $("#activity-choice li").hide();
                        $("#activity-choice li.stream").show();

                        $("ul.dropdown-menu li").click(function(event)
                        {
                            // hide all
                            $("#activity-choice li").hide();
                            $("ul.dropdown-menu li").removeClass("active");

                            // show selected
                            var attr = $(event.target).attr("class");
                            $("#activity-choice li." + attr).show();
                            $(event.target).parent().addClass("active");
                        });

                        // Set loading bars
                        $("#radar-graph, #livestream, #time-donut-wrapper, #time-graph, li.activity").append($('<div class="load5 loadimage"><div class="loader"></div></div>'));
                        $(".heatmap").append($('<div class="load5 loadimage" style="margin-top: 60px;"><div class="loader"></div></div>'));

                        require(["app/controllers/dashboard_live",
                                "app/controllers/dashboard_sequencer",
                                "app/controllers/dashboard_heatmap",
                                "app/controllers/dashboard_pie",
                                "app/controllers/dashboard_graph",
                                "app/controllers/dashboard_radar"
                            ],
                            function(Streamer, Sequencer, Heatmap, Pie, Graph, Radar)
                            {
                                var webrtc_enabled = "{{$webrtc_enabled}}";
                                var stream_type = "{{$stream_type}}";
                                console.log("stream type " + stream_type);
                                if(stream_type =='WebRTCStream' && webrtc_enabled == 'true'){

                                    $(window).bind("beforeunload", function() {
                                        stop();
                                        console.log("webrtc stopped");
                                    });

                                    $("#livestream_webrtc").show();
                                    $("#remoteVideo").height($("#livestream").width()/2);
                                    startWebSocket();
                                }
                                else{
                                    $("#livestream_webrtc").hide();
                                    $.get('api/v1/stream',function(data)
                                    {
                                        Streamer.initialize(
                                            {
                                                element: "livestream",
                                                host: data.url,
                                                port: data.port,
                                                width: '100%',
                                                callback: function(){}
                                            });
                                    })
                                }

                                Sequencer.initialize(
                                    {
                                        element: "canvas",
                                        direction:"-x",
                                        progressMode: "bar",
                                        progressShowImages: true,
                                        playMode: "loop",
                                        playInterval: 300,
                                        fps: "{{$fps}}",
                                        url: _baseUrl + "/api/v1/images/latest_sequence",
                                        callback: function()
                                        {
                                            Heatmap.initialize(
                                                {
                                                    element: "heatmap",
                                                    url: _baseUrl + "/api/v1/images/regions",
                                                    urlSequence: _baseUrl + "/api/v1/images/latest_sequence",
                                                    fps: "1",
                                                    radius: "{{$kerberos['radius']}}",
                                                    callback: function(){}
                                                });

                                            $("ul.dropdown-menu li a.heat").click(function(event)
                                            {
                                                Heatmap.redraw();
                                            });

                                            if ($(window).width() >= 768)
                                            {
                                                Pie.initialize(
                                                    {
                                                        url: "/api/v1/images/perday/3",
                                                        callback: function()
                                                        {
                                                            Graph.initialize(
                                                                {
                                                                    url: _baseUrl + "/api/v1/images/perhour/3",
                                                                    callback: function()
                                                                    {
                                                                        Radar.initialize(
                                                                            {
                                                                                url: _baseUrl + "/api/v1/images/perweekday/1",
                                                                                callback: function (){}
                                                                            });

                                                                        Radar.redraw();
                                                                    }
                                                                });

                                                            Graph.redraw();
                                                        }
                                                    });

                                                Pie.redraw();
                                            }
                                        }
                                    });

                                $("ul.dropdown-menu li a.activity").click(function(event)
                                {
                                    Sequencer.redraw();
                                });
                            });
                    });
                });
            </script>
            <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </div>
    <!-- /#page-wrapper -->
@stop
