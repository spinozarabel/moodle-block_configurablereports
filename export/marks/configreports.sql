-- ver 1.2 marks card export grades 8-12
-- Check for blank or null middle name of user
-- Start the SQL code
SELECT u.username AS 'username',
-- check to see if middle name is null or blank if so ignore it, else include it
CASE
  WHEN (u.MiddleName <=> ''  OR u.MiddleName IS NULL) THEN CONCAT(u.FirstName,' ',u.LastName)
  ELSE CONCAT(u.FirstName,' ',u.MiddleName,' ',u.LastName)
END AS 'fullname' ,
-- CONCAT(u.firstname , ' ' , u.middlename, ' ' , u.lastname) AS 'fullname',
u.id AS 'id',
u.idnumber AS 'idnumber',
'9W' AS "gradesection",

CASE
  WHEN gi.itemtype = 'course' THEN CONCAT(c.fullname, '')
  ELSE gi.itemname
END AS 'subject',

ROUND(gg.finalgrade,2) AS 'markspercentage',
c.id AS 'courseid'

-- ROUND(gg.finalgrade / gg.rawgrademax * 100 ,2) AS Percentage
-- FROM_UNIXTIME(gg.timemodified) AS TIME

FROM prefix_course AS c
JOIN prefix_context AS ctx ON c.id = ctx.instanceid
JOIN prefix_role_assignments AS ra ON ra.contextid = ctx.id
JOIN prefix_user AS u ON u.id = ra.userid
JOIN prefix_grade_grades AS gg ON gg.userid = u.id
JOIN prefix_grade_items AS gi ON gi.id = gg.itemid
JOIN prefix_course_categories AS cc ON cc.id = c.category

WHERE  	gi.courseid = c.id          AND
		    gi.itemtype =     'course'  AND
		    cc.name     <>    "Classes" AND
		    c.shortname LIKE  '%G9W%'
ORDER BY fullname
