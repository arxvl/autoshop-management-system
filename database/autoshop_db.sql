/*Automotive Repair Management System 
for Patrick Auto Repair Shop
*/

DROP TABLE IF EXISTS Customer_T;
DROP TABLE IF EXISTS Vehicle_T;

CREATE TABLE User_T
(
    UserID       CHAR(10)      NOT NULL,
    Username     VARCHAR(50)   NOT NULL,
    PasswordHash VARCHAR(255)  NOT NULL, /* 255 chars is standard for PHP's password_hash() */
    UserRole     VARCHAR(30)   NOT NULL,

    CONSTRAINT UserPK PRIMARY KEY (UserID),
    CONSTRAINT User_Username_UQ UNIQUE (Username),
    CONSTRAINT User_Role_CHK CHECK (UserRole IN ('Admin', 'Manager', 'FrontDesk'))
);

CREATE TABLE Customer_T
(
    CustomerID      CHAR(10)    NOT NULL,
    CustomerName    VARCHAR(100)   NOT NULL,
    CustomerCPNumber VARCHAR(15)   NOT NULL,
    CustomerAddress VARCHAR(200)   NOT NULL,

    CONSTRAINT CustomerPK PRIMARY KEY (CustomerID),
    CONSTRAINT Customer_CP_UQ UNIQUE (CustomerCPNumber)
);

/*parent*/
CREATE TABLE Vehicle_T
(
    VehicleID          CHAR(10)  NOT NULL,
    CustomerID         CHAR(10)  NOT NULL,
    VehiclePlateNumber VARCHAR(20)  NOT NULL,
    VehicleModel       VARCHAR(50)  NOT NULL,
    VehicleYear        INTEGER(4)   NOT NULL,

    CONSTRAINT VehiclePK PRIMARY KEY (VehicleID),
    CONSTRAINT Vehicle_CustomerFK FOREIGN KEY (CustomerID)
        REFERENCES Customer_T (CustomerID)
        ON DELETE CASCADE,
    CONSTRAINT Vehicle_Plate_UQ UNIQUE (VehiclePlateNumber),
    CONSTRAINT Vehicle_Year_CHK CHECK (VehicleYear BETWEEN 1900 AND 2100)
);

/*subtype of vehicle(child)*/
CREATE TABLE Car_T
(
    VehicleID        CHAR(10)  NOT NULL,
    TransmissionType VARCHAR(20)  NOT NULL,
    FuelType         VARCHAR(20)  NOT NULL,
    NumberOfDoors    INTEGER(2)   NOT NULL,

    CONSTRAINT CarPK PRIMARY KEY (VehicleID),
    CONSTRAINT Car_VehicleFK FOREIGN KEY (VehicleID)
        REFERENCES Vehicle_T (VehicleID)
        ON DELETE CASCADE,
    CONSTRAINT Car_Transmission_CHK CHECK
        (TransmissionType IN ('Automatic', 'Manual', 'CVT', 'Semi-Automatic')),
    CONSTRAINT Car_FuelType_CHK CHECK
        (FuelType IN ('Gasoline', 'Diesel', 'Electric', 'Hybrid', 'LPG')),
    CONSTRAINT Car_Doors_CHK CHECK (NumberOfDoors BETWEEN 2 AND 6)
);

/*subtype of vehicle(child)*/
CREATE TABLE Motorcycle_T
(
    VehicleID          CHAR(10)  NOT NULL,
    EngineDisplacement VARCHAR(20)  NOT NULL,
    CycleType          VARCHAR(30)  NOT NULL,

    CONSTRAINT MotorcyclePK PRIMARY KEY (VehicleID),
    CONSTRAINT Motorcycle_VehicleFK FOREIGN KEY (VehicleID)
        REFERENCES Vehicle_T (VehicleID)
        ON DELETE CASCADE,
    CONSTRAINT Motorcycle_CycleType_CHK CHECK
        (CycleType IN ('Sport', 'Cruiser', 'Scooter', 'Off-Road', 'Touring', 'Standard'))
);

/*parent*/
CREATE TABLE Part_T
(
    PartID          CHAR(10)  NOT NULL,
    PartName        VARCHAR(100) NOT NULL,
    QuantityInStock INTEGER(10)  NOT NULL,
    UnitPrice       DECIMAL(10,2)  NOT NULL,

    CONSTRAINT PartPK PRIMARY KEY (PartID),
    CONSTRAINT Part_Stock_CHK CHECK (QuantityInStock >= 0),
    CONSTRAINT Part_Price_CHK CHECK (UnitPrice >= 0)
);

/*subtype of part(child)*/
CREATE TABLE Consumable_T
(
    PartID          CHAR(10)  NOT NULL,
    VolumeInLiters  INTEGER(5)   NOT NULL,
    ExpirationDate  DATE          NOT NULL,

    CONSTRAINT ConsumablePK PRIMARY KEY (PartID),
    CONSTRAINT Consumable_PartFK FOREIGN KEY (PartID)
        REFERENCES Part_T (PartID)
        ON DELETE CASCADE,
    CONSTRAINT Consumable_Volume_CHK CHECK (VolumeInLiters > 0)
);

/*subtype of part(child)*/
CREATE TABLE SparePart_T
(
    PartID         CHAR(10)  NOT NULL,
    ManufacPartNo  VARCHAR(50)  NOT NULL,

    CONSTRAINT SparePartPK PRIMARY KEY (PartID),
    CONSTRAINT SparePart_PartFK FOREIGN KEY (PartID)
        REFERENCES Part_T (PartID)
        ON DELETE CASCADE,
    CONSTRAINT SparePart_ManufacNo_UQ UNIQUE (ManufacPartNo)
);

CREATE TABLE ServiceType_T
(
    ServiceTypeID  CHAR(10)   NOT NULL,
    ServiceName    VARCHAR(100)  NOT NULL,
    Descript       VARCHAR(255),
    LaborCost      INTEGER(10)   NOT NULL,

    CONSTRAINT ServiceTypePK PRIMARY KEY (ServiceTypeID),
    CONSTRAINT ServiceType_Name_UQ UNIQUE (ServiceName),
    CONSTRAINT ServiceType_LaborCost_CHK CHECK (LaborCost >= 0)
);

CREATE TABLE Order_T
(
    OrderID CHAR(10) NOT NULL,
    CustomerID CHAR(10) NOT NULL,
    OrderDate DATE DEFAULT (CURRENT_DATE) NOT NULL,
    OrderTotalAmount INT NOT NULL,
    OrderStatus VARCHAR(30) NOT NULL,

    PRIMARY KEY (OrderID),

    FOREIGN KEY (CustomerID)
        REFERENCES Customer_T(CustomerID)
);

CREATE TABLE OrderItem_T
(
    OrderID   CHAR(10)  NOT NULL,
    PartID    CHAR(10)  NOT NULL,
    Quantity  INTEGER(10)  NOT NULL,
    Subtotal  INTEGER(10)  NOT NULL,

    CONSTRAINT OrderItemPK PRIMARY KEY (OrderID, PartID),
    CONSTRAINT OrderItem_OrderFK FOREIGN KEY (OrderID)
        REFERENCES Order_T (OrderID)
        ON DELETE CASCADE,
    CONSTRAINT OrderItem_PartFK FOREIGN KEY (PartID)
        REFERENCES Part_T (PartID),
    CONSTRAINT OrderItem_Qty_CHK CHECK (Quantity > 0),
    CONSTRAINT OrderItem_Subtotal_CHK CHECK (Subtotal >= 0)
);

CREATE TABLE ServiceRecord_T
(
    ServiceRecordID  CHAR(10)  NOT NULL,
    CustomerID       CHAR(10)  NOT NULL,
    VehicleID        CHAR(10)  NOT NULL,
    OrderID          CHAR(10),
    DateReceived     DATE          NOT NULL,
    DateCompleted    DATE,
    Stat             VARCHAR(30)  NOT NULL,
    TotalLaborCost   INTEGER(10)  DEFAULT 0 NOT NULL,
    TotalPartsCost   INTEGER(10)  DEFAULT 0 NOT NULL,
    Notes            VARCHAR(500),

    CONSTRAINT ServiceRecordPK PRIMARY KEY (ServiceRecordID),
    CONSTRAINT ServiceRecord_CustomerFK FOREIGN KEY (CustomerID)
        REFERENCES Customer_T (CustomerID),
    CONSTRAINT ServiceRecord_VehicleFK FOREIGN KEY (VehicleID)
        REFERENCES Vehicle_T (VehicleID),
    CONSTRAINT ServiceRecord_OrderFK FOREIGN KEY (OrderID)
        REFERENCES Order_T (OrderID),
    CONSTRAINT ServiceRecord_Status_CHK CHECK
        (Stat IN ('Pending', 'In Progress', 'Completed', 'Cancelled')),
    CONSTRAINT ServiceRecord_LaborCost_CHK CHECK (TotalLaborCost >= 0),
    CONSTRAINT ServiceRecord_PartsCost_CHK CHECK (TotalPartsCost >= 0),
    CONSTRAINT ServiceRecord_Dates_CHK CHECK
        (DateCompleted IS NULL OR DateCompleted >= DateReceived)
);

CREATE TABLE PartsUsed_T
(
    ServiceRecordID  CHAR(10)  NOT NULL,
    PartID           CHAR(10)  NOT NULL,
    QuantityUsed     INTEGER(10)  NOT NULL,
    Subtotal         INTEGER(10)  NOT NULL,

    CONSTRAINT PartsUsedPK PRIMARY KEY (ServiceRecordID, PartID),
    CONSTRAINT PartsUsed_ServiceRecordFK FOREIGN KEY (ServiceRecordID)
        REFERENCES ServiceRecord_T (ServiceRecordID)
        ON DELETE CASCADE,
    CONSTRAINT PartsUsed_PartFK FOREIGN KEY (PartID)
        REFERENCES Part_T (PartID),
    CONSTRAINT PartsUsed_Qty_CHK CHECK (QuantityUsed > 0),
    CONSTRAINT PartsUsed_Subtotal_CHK CHECK (Subtotal >= 0)
);

CREATE TABLE RepairService_T
(
    ServiceRecordID  CHAR(10)  NOT NULL,
    ServiceTypeID    CHAR(10)  NOT NULL,
    LaborCost        DECIMAL(10,2)  NOT NULL,
    HoursWorked      INTEGER(5)   NOT NULL,

    CONSTRAINT RepairServicePK PRIMARY KEY (ServiceRecordID, ServiceTypeID),
    CONSTRAINT RepairService_ServiceRecordFK FOREIGN KEY (ServiceRecordID)
        REFERENCES ServiceRecord_T (ServiceRecordID)
        ON DELETE CASCADE,
    CONSTRAINT RepairService_ServiceTypeFK FOREIGN KEY (ServiceTypeID)
        REFERENCES ServiceType_T (ServiceTypeID),
    CONSTRAINT RepairService_LaborCost_CHK CHECK (LaborCost >= 0),
    CONSTRAINT RepairService_Hours_CHK CHECK (HoursWorked > 0)
);

/*parent*/
CREATE TABLE Payment_T
(
    PaymentID      CHAR(10)  NOT NULL,
    OrderID        CHAR(10)  NOT NULL,
    PaymentDate    DATE          DEFAULT (CURRENT_DATE) NOT NULL,
    TotalAmount    DECIMAL(10,2)  NOT NULL,
    AmountPaid     DECIMAL(10,2)  NOT NULL,
    PaymentStatus  VARCHAR(30)  NOT NULL,
    PaymentMethod  VARCHAR(30)  NOT NULL,

    CONSTRAINT PaymentPK PRIMARY KEY (PaymentID),
    CONSTRAINT Payment_OrderFK FOREIGN KEY (OrderID)
        REFERENCES Order_T (OrderID),
    CONSTRAINT Payment_TotalAmount_CHK CHECK (TotalAmount >= 0),
    CONSTRAINT Payment_AmountPaid_CHK CHECK (AmountPaid >= 0),
    CONSTRAINT Payment_Status_CHK CHECK
        (PaymentStatus IN ('Pending', 'Paid', 'Partial', 'Refunded', 'Cancelled')),
    CONSTRAINT Payment_Method_CHK CHECK
        (PaymentMethod IN ('Cash', 'GCash'))
);

/*subtype of payment(child)*/
CREATE TABLE Cash_T
(
    PaymentID     CHAR(10)  NOT NULL,
    AmountOffer   DECIMAL(10,2)  NOT NULL,
    ChangeAmount  DECIMAL(10,2)  NOT NULL,

    CONSTRAINT CashPK PRIMARY KEY (PaymentID),
    CONSTRAINT Cash_PaymentFK FOREIGN KEY (PaymentID)
        REFERENCES Payment_T (PaymentID)
        ON DELETE CASCADE,
    CONSTRAINT Cash_AmountOffer_CHK CHECK (AmountOffer >= 0),
    CONSTRAINT Cash_Change_CHK CHECK (ChangeAmount >= 0)
);

/*subtype of payment(child)*/
CREATE TABLE GCash_T
(
    PaymentID    CHAR(10)   NOT NULL,
    RefNumber    VARCHAR(50)   NOT NULL,
    GCashName    VARCHAR(100)  NOT NULL,
    GCashNumber  VARCHAR(15)   NOT NULL,

    CONSTRAINT GCashPK PRIMARY KEY (PaymentID),
    CONSTRAINT GCash_PaymentFK FOREIGN KEY (PaymentID)
        REFERENCES Payment_T (PaymentID)
        ON DELETE CASCADE,
    CONSTRAINT GCash_RefNumber_UQ UNIQUE (RefNumber)
);

CREATE TABLE Mechanic_T
(
    MechanicID    CHAR(10)   NOT NULL,
    MechanicName  VARCHAR(100)  NOT NULL,
    CPNumber      VARCHAR(15)   NOT NULL,
    HireDate      DATE           NOT NULL,

    CONSTRAINT MechanicPK PRIMARY KEY (MechanicID),
    CONSTRAINT Mechanic_CP_UQ UNIQUE (CPNumber)
);

CREATE TABLE MechanicSkill_T
(
    MechanicID     CHAR(10)  NOT NULL,
    ServiceTypeID  CHAR(10)  NOT NULL,
    SkillLevel     VARCHAR(30)  NOT NULL,

    CONSTRAINT MechanicSkillPK PRIMARY KEY (MechanicID, ServiceTypeID),
    CONSTRAINT MechanicSkill_MechanicFK FOREIGN KEY (MechanicID)
        REFERENCES Mechanic_T (MechanicID)
        ON DELETE CASCADE,
    CONSTRAINT MechanicSkill_ServiceTypeFK FOREIGN KEY (ServiceTypeID)
        REFERENCES ServiceType_T (ServiceTypeID),
    CONSTRAINT MechanicSkill_Level_CHK CHECK
        (SkillLevel IN ('Beginner', 'Intermediate', 'Advanced', 'Expert'))
);

CREATE TABLE MechanicAssignment_T
(
    MechanicID       CHAR(10)  NOT NULL,
    ServiceRecordID  CHAR(10)  NOT NULL,
    DateAssigned     DATE          NOT NULL,
    HoursWorked      DECIMAL(5,2)   NOT NULL,

    CONSTRAINT MechanicAssignmentPK PRIMARY KEY (MechanicID, ServiceRecordID),
    CONSTRAINT MechanicAssign_MechanicFK FOREIGN KEY (MechanicID)
        REFERENCES Mechanic_T (MechanicID)
        ON DELETE CASCADE,
    CONSTRAINT MechanicAssign_ServiceRecordFK FOREIGN KEY (ServiceRecordID)
        REFERENCES ServiceRecord_T (ServiceRecordID)
        ON DELETE CASCADE,
    CONSTRAINT MechanicAssign_Hours_CHK CHECK (HoursWorked > 0)
);

CREATE TABLE MechanicGeneralSkill_T
(
    MechanicID  CHAR(10)   NOT NULL,
    Skill       VARCHAR(100)  NOT NULL,

    CONSTRAINT MechanicGenSkillPK PRIMARY KEY (MechanicID, Skill),
    CONSTRAINT MechanicGenSkill_MechanicFK FOREIGN KEY (MechanicID)
        REFERENCES Mechanic_T (MechanicID)
        ON DELETE CASCADE
);

