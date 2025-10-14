<!-- Add subscription Form  -->
<h2>Get notified of new posts:</h2>
<form id="newsletter" method="post" action="<?php echo esc_url( post_notification_get_link() ); ?>" style="text-align:left">
    <p>
        <label for="email">Email:</label>
        <input type="email" id="email" name="addr" size="25" maxlength="50"
               value="<?php echo esc_attr( post_notification_get_addr() ); ?>" required/>
        <input type="submit" name="submit" value="Submit"/>
    </p>
</form>

<!-- Show number of subscribers -->
<h2>Number of Mail-Subscribers</h2>
<p><?php echo absint( post_notification_get_subscribers() ); ?></p>