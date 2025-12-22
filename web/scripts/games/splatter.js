function putPixel(imageData, row, col, pixel) {
    imageData.data[row * imageData.width * 4 + col * 4 + 0] = pixel[0];
    imageData.data[row * imageData.width * 4 + col * 4 + 1] = pixel[1];
    imageData.data[row * imageData.width * 4 + col * 4 + 2] = pixel[2];
    imageData.data[row * imageData.width * 4 + col * 4 + 3] = 255;
}

function getSplatter(image, centerRow, centerCol, d, R, G, B) {
    let pixels = [];
    let N = image.length, M = image[0].length;
    for (let row = Math.max(0, centerRow - d); row <= Math.min(N - 1, centerRow + d); row++) {
        for (let col = Math.max(0, centerCol - d); col <= Math.min(M - 1, centerCol + d); col++) {
            let dist = Math.sqrt((row - centerRow) * (row - centerRow) + (col - centerCol) * (col - centerCol));
            let chance = 1.0 - Math.pow(Math.min(1.0, Math.max(0.0, (dist - d * 0.6) / (d * 0.4))), 2);
            if (Math.random() < chance) {
                pixels.push([row, col]);
            }
        }
    }
    return pixels;
}

async function drawSplatterVisualisation(replayLog) {
    const data = replayLog.split("\n");

    let numRows = Number(data[0].split(" ")[0]);
    let numCols = Number(data[0].split(" ")[1]);
    let seed = Number(data[0].split(" ")[2]);
    let usedQueries = Number(data[1]);

    let canvas = document.getElementById('imageCanvas');
    canvas.height = numRows + 2;
    canvas.width = numCols + 2;
    let ctx = canvas.getContext('2d');
    ctx.fillStyle = 'rgb(253, 253, 253)';
    ctx.fillRect(0, 0, numCols, numRows);

    let dataIdx = 2;
    let queries = [];
    for (let i = 1; i <= usedQueries; i++) {
        queries.push(data[dataIdx++].split(" ").map((el) => parseInt(el)));
    }

    let image = [];
    for (let row = 0; row < numRows; row++) {
        let imageRow = [];
        for (let col = 0; col < numCols; col++) {
            imageRow.push([255, 255, 255]);
        }
        image.push(imageRow);
    }

    let sleepTime = 100;
    let imageData = ctx.getImageData(0, 0, numCols, numRows);
    let curQEl = document.getElementById('curQ');
    for (let i = 0; i < usedQueries; i++) {
        let row = queries[i][0];
        let col = queries[i][1];
        let d = queries[i][2];
        let R = queries[i][3];
        let G = queries[i][4];
        let B = queries[i][5];
        let shot = getSplatter(image, row, col, d, R, G, B);
        for (let c = 0; c < shot.length; c++) {
            putPixel(imageData, shot[c][0], shot[c][1], [R, G, B]);
        }
        curQEl.innerHTML = '' + (i + 1) + '/' + 10000;
    
        if (i <= 500 || i % 10 === 9) {
            ctx.putImageData(imageData, 0, 0);
            await sleep(sleepTime);
            sleepTime = Math.max(sleepTime - 1, 5);
        }
    }
    ctx.putImageData(imageData, 0, 0);
    curQEl.innerHTML = '' + usedQueries + '/' + 10000;

    /*
    await sleep(2000);

    for (let i = 200; i >= 5; i--) {
        canvas.style.opacity = String(i / 200.0);
        await sleep(10);
    }

    for (let row = 0; row < numRows; row++) {
        for (let col = 0; col < numCols; col++) {
            putPixel(imageData, row, col, result[row][col]);
        }
    }
    ctx.putImageData(imageData, 0, 0);

    for (let i = 5; i <= 200; i++) {
        canvas.style.opacity = String(i / 200.0);
        await sleep(10);
    }

    await sleep(2000);

    for (let col = 0; col <= numCols; col++) {
        // "Sweep" line that reveals the actual image
        if (col < numCols) {
            for (let row = 0; row < numRows; row++) {
                putPixel(imageData, row, col, [255, 255, 255]);
            }
        }
        // The column of the actual image
        if (col - 1 >= 0) {
            for (let row = 0; row < numRows; row++) {
                putPixel(imageData, row, col - 1, image[row][col]);
            }
        }
        if (col % 3 == 1 || col == numCols) {
            ctx.putImageData(imageData, 0, 0);
            await sleep(1);
        }
    }
    */
}

function getSplatterContent(playerName) {
    // Now create the DOM content
    var content = document.createElement('div');
    content.className = 'splatter-content';

    // Header with the task name
    var header = document.createElement('div');
    header.style.textAlign = 'left';
    header.innerHTML = '<h2><span class="blue">Splatter</span><br>Contestant ' + playerName + '</h2>';
    content.appendChild(header);

    // The image
    var imagePlaceholder = document.createElement('div');
    imagePlaceholder.style = 'margin-top: 1rem; vertical-align: middle; text-align: center;';
    imagePlaceholder.id = 'imagePlaceholder';

    var canvas = document.createElement('canvas');
    canvas.height = 512;
    canvas.width = 512;
    canvas.id = 'imageCanvas';

    imagePlaceholder.appendChild(canvas);
    content.appendChild(imagePlaceholder);

    // Query info (current query / allowed queries)
    var qInfo = document.createElement('div');
    qInfo.id = 'qInfo';
    qInfo.style = 'vertical-align: middle;';
    qInfo.innerHTML += '<div style="text-align: center; font-weight: bold; font-size: 1rem;" id="curQ">&nbsp; </div>';
    content.appendChild(qInfo);

    return content;
}

function showSplatterReplay(userName, replayLog) {
    var content = getSplatterContent(userName);
    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/replays'));
    showActionForm(content.outerHTML, gameUrl);
    drawSplatterVisualisation(replayLog);
}
