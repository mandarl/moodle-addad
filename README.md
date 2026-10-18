# README #

LOCAL_ADDAD

This plugin was developed to hook the course_completion event and use it to add the user to a Active Directory group specified in a custom field of the course.


## Committee chair sync

Keeps committee chairs as managers of their committee's course category, so courses stay editable when
their original teacher leaves.

Configure under Site administration > Plugins > Local plugins > AddAD:

- **Chair groups**: one `category idnumber = AD group name[, AD group name]` per line, for example
  `EXAMPLE = Example Committee Chair, Example Online Course Editors`. List a second group that the chair manages
  to let them delegate editing without a Moodle admin.
- **Chair role**: shortname of the role to assign (default `manager`).

Every member of the AD group (nested groups included) gets the role on that top-level category, and loses it
when removed from the group. This runs every 15 minutes as a scheduled task. If any listed group can't be read, that category is skipped and nothing is removed.
Only role assignments created by this plugin are changed; roles assigned by hand are left alone.

To preview or run it now:

    php local/addad/cli/sync_chair_roles.php --dry-run
    php local/addad/cli/sync_chair_roles.php --run


## Tell the member portal about new courses

When a course is created or edited in a way that can publish it (shown or hidden, moved to another
category), when a category is edited, or when an enrolment method is added or changed, the plugin
queues one ad hoc task. The task sends the DMS member portal a signed "the course catalog changed"
request. The portal re-reads the catalog, works out which courses are newly published, and notifies
members who follow that committee. The request carries no course data; the portal does not trust it
for anything beyond "look again".

Configure under Site administration > Plugins > Local plugins > AddAD:

- **Portal webhook URL**: for example `https://members.example.org/webhook/moodle`. **Empty switches the
  feature off** (the observers do nothing and a task that is already queued becomes a no-op), so this is
  also the kill switch.
- **Portal webhook secret**: the shared secret that signs each request. It must equal the portal's
  `ENV_MOODLE_WEBHOOK_SECRET`.

Each request is `POST` with a small JSON body (`{"event":"course_change","time":<unix time>}`) and the
header `X-Addad-Signature: sha256=<hex HMAC-SHA256 of the raw body>`. A non-2xx answer or a network error
makes the task fail and Moodle retries it later with a growing delay; many events before cron runs it
collapse into one pending task. Observers never throw, so a problem here cannot break a course edit.

To check the connection (the portal's first ping also records the courses that already exist, so it never
announces them):

    sudo -u www-data php local/addad/cli/announce_courses.php --dry-run
    sudo -u www-data php local/addad/cli/announce_courses.php --send
