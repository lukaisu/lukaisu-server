# Wordpress Integration

* **IMPORTANT: The WordPress feature is a legacy function no longer maintained. Use at your own risk!**

lang-learn-guy:
> I CANNOT give any support for this feature, NOR can I help you with any WordPress problems!
**USE AT YOUR OWN RISK!**

Hugo:
> The WordPress feature is a legacy function no longer maintained. It should be considered experimental as it may break with a new release of Lukaisu Server.

The following instructions are for users who have installed WordPress, and want to install Lukaisu Server for multiple WordPress users in conjunction with WordPress authentication. Every WordPress user will have his/her own Lukaisu Server table set.  

1. [Download](https://wordpress.org/) and install WordPress.
2. [Download](https://github.com/lukaisu/lukaisu-server/) and install Lukaisu Server into a new subdirectory "lukaisu-server", located in the main directory of your WordPress installation.
3. In subdirectory "lukaisu-server", copy `.env.example` to `.env`, and enter the database parameters DB_HOST (database server), DB_USER (database user id), DB_PASSWORD (database password), and DB_NAME (database name, can be the same as your WordPress database, or a different one) by editing the file with a text editor.
4. In the WordPress General Settings, decide whether anyone can register and use Lukaisu Server (Membership = "Anyone can register"), or not (an administrator must create new users). The "New User Default Role" should be "Subscriber".
5. The link to start Lukaisu Server with **complete** WordPress authentication is:  
    _http&#58;&#47;&#47;...path-to-wp-blog.../lukaisu-server/wp\_lukaisu\_start.php_
6. The link to start Lukaisu Server (without WordPress authentication, only by checking the session cookie that is valid until the browser is closed) is:  
    _http&#58;&#47;&#47;...path-to-wp-blog.../lukaisu-server/_  
    If the session cookie does not exist, both above start methods are the same.
7. To properly log out from both WordPress and Lukaisu Server, use the link:  
    _http&#58;&#47;&#47;...path-to-wp-blog.../lukaisu-server/wp\_lukaisu\_stop.php_  
    The Lukaisu Server home page has such a link. If you only log out via the links on the WordPress pages, you will still be able to use Lukaisu Server until the browser is closed. If you want to log out from both WordPress and Lukaisu Server, use the above link, or click on the link on the Lukaisu Server home page!
8. If you delete a user, you must find out its user number (table "wp\_users"). After deleting the user in WordPress, you can delete all Lukaisu Server tables with table names beginning with the user number plus an underscore "\_". You can do this in phpMyAdmin.
