# Accessing the threadlog

In case you lose the link for some reason, threadlogs can be accessed with the following link (sub UID with the user ID you'd like to view):

	<a href="member.php?action=profile&show=threadlog&uid=UID">Click me!</a>

# Template Variables

## threadlog

<dl>
	<dt>{$threads}</dt>
	<dd>outputs either <code>threadlog_row</code> or <code>threadlog_nothreads</code></dd>
</dl>

## threadlog_row

<dl>
	<dt>{$class}</dt>
	<dd>outputs "archived", "active", or "dead"</dd>
	<dt>{$threadlink}</dt>
	<dd>outputs an anchor tag with the thread subject</dd>
	<dt>{$lastpostdate}</dt>
	<dd>outputs date of last post in the thread (format modified by configuration -&gt; date and time -&gt; date format)</dd>
	<dt>{$lastposter}</dt>
	<dd>outputs plain text version of the last user to post</dd>
	<dt>{$prefix}</dt>
	<dd>outputs thread prefix</dd>
	<dt>{$participants}</dt>
	<dd>outputs comma-separated list of participants, excluding the current user</dd>
	<dt>{$count_archived}</dt>
	<dd>counts number of archived threads</dd>
	<dt>{$count_active}</dt>
	<dd>counts number of active threads</dd>
	<dt>{$count_dead}</dt>
	<dd>counts number of dead threads</dd>
	<dt>{$count_total}</dt>
	<dd>counts number of total threads</dd>
	<dt>{$xthreads['field']}</dt>
	<dd>outputs specified xthreads field (relies on <a href="https://github.com/zingaburga/XThreads-MyBB-Plugin">XThreads plugin</a>)</dd>
	<dt>{$usernotes['field']}</dt>
	<dd>outputs specified usernotes field (relies on <a href="https://github.com/amwelles/mybb-usernotes">usernotes plugin</a>)</dd>
</dl>

## threadlog_nothreads

This template has no variables.