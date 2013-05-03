quiz
====

A simple quiz application. Questions are stored in an .xml and results in an sqlite database.

(The texts in the UI are in Romanian language; sorry but no i8n yet...)

Installation
------------

Use [composer](http://getcomposer.org/) to install dependencies:

	php composer.phar install

Make writable the `data` directory. This is where the sqlite database will be created automatically:

	chmod 777 data

Copy `dev/sample-questions.xml` to `data/questions.xml` and edit your questions.
