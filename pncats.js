var post_notification_running = false;
var post_notification_box = [];

post_notification_cats_init = function () {
    var boxes = document.getElementsByTagName("input");
    var tocheck = "";
    var i;
    if (post_notification_running) return;
    post_notification_running = true;


    for (i = 0; i < boxes.length; i++) {
        if (boxes[i].id.substring(0, 4) === "cat.") {
            if (boxes[i].disabled === true) {
                boxes[i].checked = post_notification_box[i];
                boxes[i].disabled = false;
            }
        }
    }


    for (i = 0; i < boxes.length; i++) {
        if (boxes[i].id.substring(0, 4) === "cat.") {
            if (tocheck !== "") {
                // hack by gwegner.de
                if ((boxes[i].id.substring(0, tocheck.length) === tocheck)
                    && (boxes[i].id.length > tocheck.length)
                    && (boxes[i].id.substring(tocheck.length, tocheck.length + 1) === "."))
                    // end of hack
                {
                    post_notification_box[i] = boxes[i].checked;
                    boxes[i].checked = true;
                    boxes[i].disabled = true;
                } else {
                    tocheck = "";
                }
            }

            if (tocheck === "") { //There is no string
                if (boxes[i].checked === true) {
                    tocheck = boxes[i].id; //From now on this is the new String
                }
            }
        }
    }
    post_notification_running = false;
}


post_notification_cats_change = function () {

}

jQuery(document).ready(function () {
    jQuery('input:radio[name="action"]').change(
        function () {
            if (jQuery(this).is(':checked') && jQuery(this).val() === 'unsubscribe') {
                jQuery('.postnotification_cats').css('visibility', 'hidden');
                console.log("hide");
            } else {
                jQuery('.postnotification_cats').css('visibility', 'visible');
            }
        });

    // Beim Submit des Formulars alle disabled Checkboxen aktivieren
    jQuery('form').on('submit', function() {
        var boxes = document.getElementsByTagName("input");
        for (var i = 0; i < boxes.length; i++) {
            if (boxes[i].id.substring(0, 4) === "cat." && boxes[i].disabled) {
                boxes[i].disabled = false;
            }
        }
    });
});