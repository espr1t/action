async function drawImage(replayLog) {
    const data = replayLog.split("\n");

    let numRows = Number(data[0].split(" ")[0]);
    let numCols = Number(data[0].split(" ")[1]);
    let maxQueries = Number(data[0].split(" ")[2]);
    let usedQueries = Number(data[1]);

    let canvas = document.getElementById('imageCanvas');
    let ctx = canvas.getContext('2d');

    // Total update should take 3 seconds
    const updateInterval = 100; // milliseconds
    const updateEvery = Math.round(usedQueries / (3000 / updateInterval));

    let dataIdx = 2;
    let curQueryEl = document.getElementById('curQuery');
    for (let i = 1; i <= usedQueries; i++) {
        let tokens = data[dataIdx++].split(" ");
        let hitRow = Number(tokens[4]);
        let hitCol = Number(tokens[5]);
        if (hitRow !== -1 && hitCol !== -1) {
            ctx.fillStyle = 'rgb(0, 0, 0)';
            ctx.fillRect(hitCol, hitRow, 1, 1);
        }
        if (i % updateEvery === 0) {
            curQueryEl.innerHTML = '' + i + '/' + maxQueries;
            await sleep(updateInterval);
        }
    }
    curQueryEl.innerHTML = '' + usedQueries + '/' + maxQueries;

    let imageData = ctx.getImageData(0, 0, numCols, numRows);
    let imageDataIdx = 0;
    for (let row = 0; row < numRows; row++) {
        let ansData = data[usedQueries + row + 2];
        for (let col = 0; col < numCols; col++) {
            imageData.data[imageDataIdx++] = (ansData[col] === '1') ? 42 : 255;
            imageData.data[imageDataIdx++] = (ansData[col] === '1') ? 42 : 255;
            imageData.data[imageDataIdx++] = (ansData[col] === '1') ? 42 : 255;
            imageData.data[imageDataIdx++] = 255;
        }
    }
    ctx.putImageData(imageData, 0, 0);

    await sleep(2000);

    for (let opacity = 0; opacity <= 100; opacity++) {
        let imageDataIdx = 0;
        for (let row = 0; row < numRows; row++) {
            let ansData = data[usedQueries + row + 2];
            let solData = data[usedQueries + row + 514];
            for (let col = 0; col < numCols; col++) {
                let R = ((ansData[col] === '1') ? 42 : 255);
                let G = ((ansData[col] === '1') ? 42 : 255);
                let B = ((ansData[col] === '1') ? 42 : 255);
                if (ansData[col] !== solData[col]) {
                    R = (R * (100 - opacity) + 216 * opacity) / 100;
                    G = (G * (100 - opacity) + 74 * opacity) / 100;
                    B = (B * (100 - opacity) + 56 * opacity) / 100;
                }
                imageData.data[imageDataIdx++] = R;
                imageData.data[imageDataIdx++] = G;
                imageData.data[imageDataIdx++] = B;
                imageData.data[imageDataIdx++] = 255;
            }
        }
        ctx.putImageData(imageData, 0, 0);
        await sleep(20);
    }

    for (let opacity = 100; opacity >= 0; opacity--) {
        let imageDataIdx = 0;
        for (let row = 0; row < numRows; row++) {
            let ansData = data[usedQueries + row + 2];
            let solData = data[usedQueries + row + 514];
            for (let col = 0; col < numCols; col++) {
                let R = ((solData[col] === '1') ? 42 : 255);
                let G = ((solData[col] === '1') ? 42 : 255);
                let B = ((solData[col] === '1') ? 42 : 255);
                if (ansData[col] !== solData[col]) {
                    R = (R * (100 - opacity) + 216 * opacity) / 100;
                    G = (G * (100 - opacity) + 74 * opacity) / 100;
                    B = (B * (100 - opacity) + 56 * opacity) / 100;
                }
                imageData.data[imageDataIdx++] = R;
                imageData.data[imageDataIdx++] = G;
                imageData.data[imageDataIdx++] = B;
                imageData.data[imageDataIdx++] = 255;
            }
        }
        ctx.putImageData(imageData, 0, 0);
        await sleep(20);
    }
}

function getReconstructContent(playerName) {
    // Now create the DOM content
    var content = document.createElement('div');
    content.className = 'reconstruct-content';

    // Header with the task name
    var header = document.createElement('div');
    header.style.textAlign = 'left';
    header.innerHTML = '<h2><span class="blue">Reconstruct</span><br>Contestant ' + playerName + '</h2>';
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

function showReconstructReplay(userName, replayLog) {
    var content = getReconstructContent(userName);
    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/replays'));
    showActionForm(content.outerHTML, gameUrl);
    drawImage(replayLog);
}
