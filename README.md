# README #

LOCAL_ADDAD

This plugin was developed to hook the course_completion event and use it to add the user to a Active Directory group specified in a custom field of the course.


## Committee chair sync

Keeps committee chairs as managers of their committee's course category, so courses stay editable when
their original teacher leaves.

Configure under Site administration > Plugins > Local plugins > AddAD:

- **Chair groups**: one `category idnumber = AD group name` per line, for example `EXAMPLE = Example Committee Chair`.
- **Chair role**: shortname of the role to assign (default `manager`).

Every member of the AD group (nested groups included) gets the role on that top-level category, and loses it
when removed from the group. This runs hourly as a scheduled task. If the AD lookup fails, nothing is removed.
Only role assignments created by this plugin are changed; roles assigned by hand are left alone.

To preview or run it now:

    php local/addad/cli/sync_chair_roles.php --dry-run
    php local/addad/cli/sync_chair_roles.php --run
