async function drawImageScannerVisualisation(replayLog) {
    const data = Array.from(replayLog.split(/\s+/).map(item => Number(item)));

    var idx = 0;
    var numRows = data[idx++];
    var numCols = data[idx++];
    var maxQueries = data[idx++];

    var canvas = document.getElementById('imageCanvas');
    canvas.height = numRows;
    canvas.width = numCols;
    var ctx = canvas.getContext('2d');
    reposition('actionForm');

    var usedQueries = data[idx++];

    // Total update should take 5 seconds
    var updateInterval = 100; // milliseconds
    var updateEvery = Math.round(usedQueries / (3000 / updateInterval));

    var curQueryEl = document.getElementById('curQuery');
    for (var i = 1; i <= usedQueries; i++) {
        var row1 = data[idx++];
        var col1 = data[idx++];
        var row2 = data[idx++];
        var col2 = data[idx++];
        var r = data[idx++];
        var g = data[idx++];
        var b = data[idx++];
        ctx.fillStyle = 'rgb(' + r + ', ' + g + ', ' + b + ')';
        ctx.fillRect(col1, row1, col2 - col1 + 1, row2 - row1 + 1);
        if (i % updateEvery == 0) {
            curQueryEl.innerHTML = '' + i + '/' + maxQueries;
            await sleep(updateInterval);
        }
    }
    curQueryEl.innerHTML = '' + usedQueries + '/' + maxQueries;

    var imageData = ctx.getImageData(0, 0, numCols, numRows), imageDataIdx = 0;

    idx += numRows * numCols * 3;
    imageDataIdx = 0;
    for (var row = 0; row < numRows; row++) {
        for (var col = 0; col < numCols; col++) {
            imageData.data[imageDataIdx++] = data[idx++];
            imageData.data[imageDataIdx++] = data[idx++];
            imageData.data[imageDataIdx++] = data[idx++];
            imageData.data[imageDataIdx++] = 255;
        }
    }
    ctx.putImageData(imageData, 0, 0);

    await sleep(3000);

    for (i = 200; i >= 10; i--) {
        canvas.style.opacity = i / 200.0;
        await sleep(10);
    }

    idx -= 2 * numRows * numCols * 3;
    imageDataIdx = 0;
    for (var row = 0; row < numRows; row++) {
        for (var col = 0; col < numCols; col++) {
            imageData.data[imageDataIdx++] = data[idx++];
            imageData.data[imageDataIdx++] = data[idx++];
            imageData.data[imageDataIdx++] = data[idx++];
            imageData.data[imageDataIdx++] = 255;
        }
    }
    ctx.putImageData(imageData, 0, 0);

    for (i = 10; i <= 200; i++) {
        canvas.style.opacity = i / 200.0;
        await sleep(10);
    }
}

function getImagescannerContent(playerName) {
    // Now create the DOM content
    var content = document.createElement('div');
    content.className = 'imagescanner-content';

    // Header with the task name
    var header = document.createElement('div');
    header.style.textAlign = 'left';
    header.innerHTML = '<h2><span class="blue">ImageScanner</span><br>Contestant ' + playerName + '</h2>';
    content.appendChild(header);

    // The image
    var imagePlaceholder = document.createElement('div');
    imagePlaceholder.style = 'margin-top: 1rem; vertical-align: middle;';
    imagePlaceholder.id = 'imagePlaceholder';

    var canvas = document.createElement('canvas');
    canvas.height = 512;
    canvas.width = 512;
    canvas.id = 'imageCanvas';

    imagePlaceholder.appendChild(canvas);
    content.appendChild(imagePlaceholder);


    // Query info (current query / allowed queries)
    var queryInfo = document.createElement('div');
    queryInfo.id = 'queryInfo';
    queryInfo.style = 'vertical-align: middle;';
    queryInfo.innerHTML += '<div style="text-align: center; font-weight: bold; font-size: 1.5rem;" id="curQuery">0/10000</div>';
    content.appendChild(queryInfo);

    return content;
}

function showImagescannerReplay(userName, replayLog) {
    var content = getImagescannerContent(userName);
    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/replays'));
    showActionForm(content.outerHTML, gameUrl);
    drawImageScannerVisualisation(replayLog);
}
