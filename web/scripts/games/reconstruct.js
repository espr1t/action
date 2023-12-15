function bresenham(tokens, ctx, fill) {
    let hitRow = Number(tokens[4]);
    let hitCol = Number(tokens[5]);
    let row1 = Number(tokens[0]);
    let col1 = Number(tokens[1]);
    let row2 = hitRow === -1 ? Number(tokens[2]) : hitRow;
    let col2 = hitCol === -1 ? Number(tokens[3]) : hitCol;

    let deltaRow = Math.abs(row2 - row1);
    let signRow = row1 < row2 ? +1 : -1;
    let deltaCol = -Math.abs(col2 - col1);
    let signCol = col1 < col2 ? +1 : -1;
    let error = deltaRow + deltaCol;

    ctx.fillStyle = fill;
    while (true) {
        ctx.fillRect(col1, row1, 1, 1);
        if (row1 === row2 && col1 === col2) {
            break;
        }
        let doubleError = 2 * error;
        if (doubleError >= deltaCol) {
            if (row1 === row2)
                break;
            error += deltaCol;
            row1 += signRow;
        }
        if (doubleError <= deltaRow) {
            if (col1 === col2)
                break;
            error += deltaRow;
            col1 += signCol;
        }
    }

    if (hitRow !== -1 && hitCol !== -1) {
        ctx.fillStyle = 'rgb(0, 0, 0)';
        ctx.fillRect(hitCol, hitRow, 1, 1);
    }
}

async function drawReconstructVisualisation(replayLog) {
    const data = replayLog.split("\n");

    let numRows = Number(data[0].split(" ")[0]);
    let numCols = Number(data[0].split(" ")[1]);
    let maxQueries = Number(data[0].split(" ")[2]);
    let usedQueries = Number(data[1]);

    let canvas = document.getElementById('imageCanvas');
    let ctx = canvas.getContext('2d');
    ctx.fillStyle = 'rgb(238, 238, 238)';
    ctx.fillRect(0, 0, 512, 512);

    // Total update should take 10 seconds
    const updateInterval = 40; // milliseconds
    const updateEvery = Math.round(usedQueries / (8000 / updateInterval));

    let dataIdx = 2;
    let curQueryEl = document.getElementById('curQuery');
    let previous = [];
    const TRAIL = 100;
    for (let i = 1; i <= usedQueries + TRAIL; i++) {
        if (i <= usedQueries) {
            let tokens = data[dataIdx++].split(" ");
            bresenham(tokens, ctx, 'rgba(0, 138, 255, 0.5)');
            previous.push(tokens);
        }
        for (let ii = i - 1; ii >= Math.max(0, i - TRAIL); ii -= TRAIL / 20) {
            if (ii < previous.length) {
                let alpha = Math.pow((i - ii) / TRAIL, 2) / 2.0;
                let fill = 'rgba(255, 255, 255, ' + alpha + ')';
                bresenham(previous[ii], ctx, fill);
            }
        }
        if (i - TRAIL >= 0 && i - TRAIL < previous.length) {
            bresenham(previous[i - TRAIL], ctx, 'rgb(255, 255, 255)');
        }

        if (i % updateEvery === 0) {
            if (i <= usedQueries) {
                curQueryEl.innerHTML = '' + i + '/' + maxQueries;
            } else {
                curQueryEl.innerHTML = '' + usedQueries + '/' + maxQueries;
            }
            await sleep(updateInterval);
        }
    }

    await sleep(2000);

    for (let i = 200; i >= 5; i--) {
        canvas.style.opacity = String(i / 200.0);
        await sleep(10);
    }

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

    for (let i = 5; i <= 200; i++) {
        canvas.style.opacity = String(i / 200.0);
        await sleep(10);
    }

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
    drawReconstructVisualisation(replayLog);
}
