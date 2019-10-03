@php
    $chart = $strategy->getTrainingProgressChart($training);
@endphp
<div class="row bdr-rad">
    <div class="col-sm-12 npl npr">
        <h4>Training Progress for
            <span title="Strategy ID: {{ $strategy->getParam('id') }} Training ID: {{ $training->id }}">
                {{ $strategy->getParam('name') }}
            </span>
        </h4>
        {!! $chart->toHTML() !!}
    </div>
</div>
<div class="row bdr-rad">
    <div class="col-sm-12 npl npr" id="trainHistory" title="Training History">
    </div>
</div>
<div class="row bdr-rad">
    <div class="col-sm-12" id="trainProgress">
        <span class="editable cap" id="trainProgressState">
        @if ('paused' === $training->status)
            Paused
        @endif
        </span>
        &nbsp; Epoch:
        <span class="editable" id="trainProgressEpoch" title="Current epoch / Last improvement at"></span>
        &nbsp; Test:
        <span class="editable" id="trainProgressTest" title="Current / Best"></span>
        &nbsp; Train <span title="Mean Squared Error Recipocal">MSER</span>:
        <span class="editable" id="trainProgressTrainMSER"></span>
        &nbsp; Verify:
        <span class="editable" id="trainProgressVerify" title="Current / Best"></span>
        &nbsp; Signals:
        <span class="editable" id="trainProgressSignals"></span>
        &nbsp; Step Up In:
        <span class="editable" id="trainProgressNoImprovement"></span>
        &nbsp; Epochs Between Tests:
        <span class="editable" id="trainProgressEpochJump"></span>
    </div>
</div>
@if ('paused' != $training->status)
    <script>
        var pollTimeout,
            verify_max = 0,
            last_epoch = 0;
        function pollStatus() {
            console.log('pollStatus() ' + $('#trainProgress').length);
            $.ajax({
                url: '/strategy.trainProgress?id={{ $strategy->getParam('id') }}',
                success: function(data) {
                    try {
                        reply = JSON.parse(data);
                    }
                    catch (err) {
                        console.log(err);
                    }
                    var state = (undefined === reply.state) ? 'queued' : reply.state;
                    $('#trainProgressState').html(state);
                    var epoch = (undefined === reply.epoch) ? 0 : reply.epoch;
                    var lie = reply.last_improvement_epoch;
                    lie = (undefined === lie) ? 0 : lie;
                    $('#trainProgressEpoch').html(epoch + ' / ' + lie);
                    $('#trainProgressTest').html(reply.test + ' / ' + reply.test_max);
                    $('#trainProgressTrainMSER').html(reply.train_mser);
                    $('#trainProgressVerify').html(reply.verify + ' / ' + reply.verify_max);
                    $('#trainProgressSignals').html(reply.signals);
                    $('#trainProgressNoImprovement').html(11 - parseInt(reply.no_improvement));
                    $('#trainProgressEpochJump').html(reply.epoch_jump);
                    var new_epoch = parseInt(reply.epoch);
                    if (new_epoch > last_epoch && $('#trainHistory').is(':visible')) {
                        last_epoch = new_epoch;
                        window.GTrader.request(
                            'strategy',
                            'trainHistory',
                            {
                                id: {{ $strategy->getParam('id') }},
                                width: $('#trainHistory').width(),
                                height: 200
                            },
                            'GET',
                            'trainHistory'
                        );
                    }
                    var new_max = parseFloat(reply.verify_max);
                    if (new_max > verify_max) {
                        verify_max = new_max;
                        if (!window.GTrader.charts.{{ $chart->getParam('name') }}.refresh) {
                            //console.log('Error: window.GTrader.charts.{{ $chart->getParam('name') }}.refresh is false',
                            //    window.{{ $chart->getParam('name') }});
                            return;
                        }
                        window.GTrader.charts.{{ $chart->getParam('name') }}.refresh();
                    }
                },
                complete: function() {
                    if ($('#trainProgress').length) {
                        pollTimeout = setTimeout(pollStatus, 3000);
                    }
                }
            });
        }
        $('#trainProgressState').html('queued');
        $('#trainProgressEpoch').html(' ... ');
        $('#trainProgressTest').html(' ... ');
        $('#trainProgressTrainMSER').html(' ... ');
        $('#trainProgressVerify').html(' ... ');
        $('#trainProgressSignals').html(' ... ');
        $('#trainProgressNoImprovement').html(' ... ');
        $('#trainProgressEpochJump').html(' ... ');
        pollStatus();

    </script>
@endif
<div class="row bdr-rad">
    <div class="col-sm-12 float-right">
        <span class="float-right">
            @if ('paused' === $training->status)
                <button onClick="window.GTrader.request(
                                        'strategy',
                                        'trainResume',
                                        'id={{ $strategy->getParam('id') }}'
                                        )"
                        type="button"
                        class="btn btn-primary btn-mini trans"
                        title="Resume Training">
                    <span class="fas fa-play"></span> Resume Training
                </button>
            @else
                <button onClick="clearTimeout(pollTimeout);
                                    window.GTrader.request(
                                        'strategy',
                                        'trainPause',
                                        'id={{ $strategy->getParam('id') }}'
                                        )"
                        type="button"
                        class="btn btn-primary btn-mini trans"
                        title="Pause Training">
                    <span class="fas fa-pause"></span> Pause Training
                </button>
            @endif
            <button onClick="window.GTrader.request(
                                'strategy',
                                'trainStop',
                                'id={{ $strategy->getParam('id') }}'
                                )"
                    type="button"
                    class="btn btn-primary btn-mini trans"
                    title="Stop Training">
                <span class="fas fa-stop"></span> Stop Training
            </button>
            <button onClick="window.GTrader.request('strategy', 'list')"
                    type="button"
                    class="btn btn-primary btn-mini trans"
                    title="Back to the List of Strategies">
                <span class="fas fa-arrow-left"></span> Back
            </button>
        </span>
    </div>
</div>
