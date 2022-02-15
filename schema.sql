CREATE TABLE "order" (
    "id"	INTEGER UNIQUE,
    "payment_id"	INTEGER NOT NULL,
    "email"	TEXT,
    "status"	TEXT,
    "created_on"	TEXT NOT NULL,
    PRIMARY KEY("id" AUTOINCREMENT)
);