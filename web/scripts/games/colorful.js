function putPixel(imageData, row, col, pixel) {
    imageData.data[row * imageData.width * 4 + col * 4 + 0] = pixel[0];
    imageData.data[row * imageData.width * 4 + col * 4 + 1] = pixel[1];
    imageData.data[row * imageData.width * 4 + col * 4 + 2] = pixel[2];
    imageData.data[row * imageData.width * 4 + col * 4 + 3] = 255;
}

function colorDiff(c1, c2) {
    return (c1[0] - c2[0]) * (c1[0] - c2[0]) +
           (c1[1] - c2[1]) * (c1[1] - c2[1]) +
           (c1[2] - c2[2]) * (c1[2] - c2[2]) ;
}

function getComponent(image, startX, startY, k) {
    let N = image.length, M = image[0].length;
    let dir = [ [-1, 0], [0, 1], [1, 0], [0, -1] ];
    let component = [[startX, startY]];
    let visited = new Set([startY * M + startX]);
    for (let idx = 0; idx < component.length; idx++) {
        let x = component[idx][0];
        let y = component[idx][1];
        for (let d = 0; d < 4; d++) {
            let nx = x + dir[d][0]; if (nx < 0 || nx >= M) continue;
            let ny = y + dir[d][1]; if (ny < 0 || ny >= N) continue;
            let key = ny * M + nx;
            if (visited.has(key))
                continue;
            visited.add(key);
            if (colorDiff(image[ny][nx], image[startY][startX]) <= k) {
                component.push([nx, ny]);
            }
        }
    }
    return component;
}

async function drawColorfulVisualisation(replayLog) {
    const data = replayLog.split("\n");

    let numRows = Number(data[0].split(" ")[0]);
    let numCols = Number(data[0].split(" ")[1]);
    let maxK = Number(data[0].split(" ")[2]);
    let maxQ = Number(data[0].split(" ")[3]);
    let usedQueries = Number(data[1]);

    let canvas = document.getElementById('imageCanvas');
    canvas.height = numRows;
    canvas.width = numCols;
    let ctx = canvas.getContext('2d');
    ctx.fillStyle = 'rgb(238, 238, 238)';
    ctx.fillRect(0, 0, numCols, numRows);

    // Total update should take 10 seconds
    // const updateInterval = 40; // milliseconds
    // const updateEvery = Math.round(usedQueries / (8000 / updateInterval));
    const updateEvery = 50;

    let dataIdx = 2;
    let queries = [];
    for (let i = 1; i <= usedQueries; i++) {
        queries.push(data[dataIdx++].split(" ").map((el) => parseInt(el)));
    }

    let result = [];
    for (let row = 0; row < numRows; row++) {
        let resultRow = [];
        let values = data[dataIdx++].split(" ");
        for (let col = 0; col < numCols; col++) {
            let color = parseInt(values[col], 16);
            resultRow.push([
                Math.floor(color / 256 / 256),
                Math.floor(color / 256) % 256,
                color % 256
            ])
        }
        result.push(resultRow);
    }

    let image = [];
    for (let row = 0; row < numRows; row++) {
        let imageRow = [];
        let values = data[dataIdx++].split(" ");
        for (let col = 0; col < numCols; col++) {
            let color = parseInt(values[col], 16);
            imageRow.push([
                Math.floor(color / 256 / 256),
                Math.floor(color / 256) % 256,
                color % 256
            ])
        }
        image.push(imageRow);
    }

    let sleepTime = 100;
    let imageData = ctx.getImageData(0, 0, numCols, numRows);
    let curQueryEl = document.getElementById('curQuery');
    for (let i = 0; i < usedQueries; i++) {
        let x = queries[i][0];
        let y = queries[i][1];
        let k = queries[i][2];
        
        let component = getComponent(image, x, y, k);
        for (let c = 0; c < component.length; c++) {
            putPixel(imageData, component[c][1], component[c][0], image[y][x]);
        }
        if (i <= 500 || i % 10 == 9) {
            curQueryEl.innerHTML = '' + (i + 1) + '/' + maxQ;
            ctx.putImageData(imageData, 0, 0);
            await sleep(sleepTime);
            sleepTime = Math.max(sleepTime - 1, 5);
        }
    }
    ctx.putImageData(imageData, 0, 0);
    curQueryEl.innerHTML = '' + usedQueries + '/' + maxQ;

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
}

function getColorfulContent(playerName) {
    // Now create the DOM content
    var content = document.createElement('div');
    content.className = 'colorful-content';

    // Header with the task name
    var header = document.createElement('div');
    header.style.textAlign = 'left';
    header.innerHTML = '<h2><span class="blue">Colorful</span><br>Contestant ' + playerName + '</h2>';
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
    var queryInfo = document.createElement('div');
    queryInfo.id = 'queryInfo';
    queryInfo.style = 'vertical-align: middle;';
    queryInfo.innerHTML += '<div style="text-align: center; font-weight: bold; font-size: 1.5rem;" id="curQuery">&nbsp; </div>';
    content.appendChild(queryInfo);

    return content;
}

function showColorfulReplay(userName, replayLog) {
    var content = getColorfulContent(userName);
    // Make pressing escape return back to the game
    var gameUrl = window.location.href.substr(0, window.location.href.lastIndexOf('/replays'));
    showActionForm(content.outerHTML, gameUrl);
    drawColorfulVisualisation(replayLog);
}
