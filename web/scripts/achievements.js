/*
 * The functions below deal with showing and hiding achievements.
 * The ways to hide the achievement pop-up are:
 *    >> wait 10 seconds (timeout may change in the future)
 *    >> press ESC
 *    >> click outside the achievement box
 */
function createAchievement(title, description) {
    var achievement = document.createElement('template');
    achievement.innerHTML = `
        <div class="achievementWrapper">
            <div class="achievementBoxWrapper">
                <div class="achievementBoxLeft"></div>
                <div class="achievementBoxLeftIn"></div>
                <div class="achievementBox">
                    <div class="achievementTagLeft">
                        ACHIEVEMENT
                    </div>
                    <div class="achievementTagRight">
                        UNLOCKED
                    </div>

                    <div class="achievementBadge">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="achievementTitleDividerTop"></div>
                    <div class="achievementTitle">` + title + `</div>
                    <div class="achievementTitleDividerBottom"></div>
                    <div class="achievementDescription">
                        -- ` + description + ` --
                    </div>
                </div>
                <div class="achievementBoxRightIn"></div>
                <div class="achievementBoxRight"></div>
                <div class="achievementBoxShadowTB"></div>
                <div class="achievementBoxShadowLR"></div>
            </div>
            <div class="achievementOverlay"></div>
        </div>
    `;
    return achievement.content.childNodes[1];
}

function showAchievement(title, description, index, total, requested=false) {
    // Add a global achievement container if none exists yet
    var achievementsContainer = document.getElementById('achievementsContainer');
    if (!achievementsContainer) {
        achievementsContainer = document.createElement('div');
        achievementsContainer.className = 'achievementsContainer';
        achievementsContainer.id = 'achievementsContainer';
        document.getElementById('wrapper').appendChild(achievementsContainer);
    }

    // Create the DOM subtree (HTML) for the achievement
    var achievement = createAchievement(title, description);

    // Append the achievement to the DOM wrapper
    achievementsContainer.appendChild(achievement);

    // Calculate the top offset of the achievement
    var windowHeight = window.innerHeight;
    var achievementHeight = achievement.clientHeight;
    var marginTop = Math.round((windowHeight - total * achievementHeight) / 2) + index * achievementHeight;

    achievement.style.marginTop = marginTop + 'px';

    // Add event handlers (escape or clicking outside the achievement)
    keyDownEventStack.push(document.onkeydown);
    document.onkeydown = function(event) {
        identifyEscKeyPressedEvent(event, function() {hideAchievement(requested);});
    };
    document.onclick = document.ontouchstart = function(event) {
        var inAchievementBox = false;
        for (var el = event.target; el != null; el = el.parentElement) {
            if (el.className.includes('achievementBox')) {
                inAchievementBox = true;
                break;
            }
        }
        if (!inAchievementBox) {
            hideAchievement(requested);
        }
    }

    var children = Array.from(achievement.children);
    // Show the fade-in animation
    for (var i = 0; i < children.length; i++) {
        if (requested) {
            // Requested achievements (shown after a user click to show them) have faster transitions
            children[i].style.transition = "opacity 0.5s ease-in-out";
        }
        setTimeout((function(child) {child.style.opacity = 1;}).bind(this, children[i]), index * 1000);
    }
    // Finally, auto-hide non-requested achievements after 10 seconds
    if (!requested) {
        setTimeout(hideAchievement.bind(this), 10000 + index * 1000);
    }
}

function hideAchievement(requested) {
    var achievements = document.getElementsByClassName('achievementWrapper');
    for (var idx = 0; idx < achievements.length; idx++) {
        var children = Array.from(achievements[idx].children);
        if (children[0].style.opacity == 1) {
            for (var i = 0; i < children.length; i++) {
                children[i].style.opacity = 0;
            }
            setTimeout(function() {
                if (achievements[idx] && achievements[idx].parentNode) {
                    achievements[idx].parentNode.removeChild(achievements[0]);
                }
            // Requested achievements (shown after a user click to show them) have faster transitions
            }, requested ? 500 : 2000);
            if (!keyDownEventStack.empty) {
                document.onkeydown = keyDownEventStack.pop();
            }
            break;
        }
    }
}
