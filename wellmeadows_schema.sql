-- Wellmeadows Hospital Database Schema
-- Wards table
CREATE TABLE Wards (
    ward_number VARCHAR(10) PRIMARY KEY,
    ward_name VARCHAR(50) NOT NULL,
    location VARCHAR(50) NOT NULL,
    total_beds INTEGER NOT NULL,
    telephone_extension VARCHAR(20) NOT NULL
);

-- Staff Positions table
CREATE TABLE Staff_Positions (
    position_id SERIAL PRIMARY KEY,
    position_name VARCHAR(50) NOT NULL UNIQUE
);

-- Salary Scales table
CREATE TABLE Salary_Scales (
    scale_id VARCHAR(10) PRIMARY KEY,
    min_salary DECIMAL(10, 2) NOT NULL,
    max_salary DECIMAL(10, 2) NOT NULL
);

-- Staff table
CREATE TABLE Staff (
    staff_number VARCHAR(10) PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    address TEXT NOT NULL,
    telephone VARCHAR(20),
    date_of_birth DATE NOT NULL,
    sex CHAR(1) CHECK (sex IN ('M', 'F')),
    nin VARCHAR(20) UNIQUE NOT NULL,
    position_id INTEGER REFERENCES Staff_Positions(position_id),
    current_salary DECIMAL(10, 2) NOT NULL,
    salary_scale_id VARCHAR(10) REFERENCES Salary_Scales(scale_id),
    ward_allocated VARCHAR(10) REFERENCES Wards(ward_number),
    hours_per_week DECIMAL(5, 2) NOT NULL,
    contract_type CHAR(1) NOT NULL CHECK (contract_type IN ('P', 'T')),
    payment_type CHAR(1) NOT NULL CHECK (payment_type IN ('W', 'M'))
);

-- Staff Qualifications table
CREATE TABLE Staff_Qualifications (
    qualification_id SERIAL PRIMARY KEY,
    staff_number VARCHAR(10) REFERENCES Staff(staff_number),
    qualification_type VARCHAR(100) NOT NULL,
    qualification_date DATE NOT NULL,
    institution VARCHAR(100) NOT NULL
);

-- Staff Work Experience table
CREATE TABLE Staff_Work_Experience (
    experience_id SERIAL PRIMARY KEY,
    staff_number VARCHAR(10) REFERENCES Staff(staff_number),
    position VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    finish_date DATE,
    organization VARCHAR(100) NOT NULL
);

-- Staff Rota table
CREATE TABLE Staff_Rota (
    rota_id SERIAL PRIMARY KEY,
    staff_number VARCHAR(10) REFERENCES Staff(staff_number),
    ward_number VARCHAR(10) REFERENCES Wards(ward_number),
    week_beginning DATE NOT NULL,
    shift_type VARCHAR(10) NOT NULL CHECK (shift_type IN ('Early', 'Late', 'Night'))
);

-- Local Doctors table
CREATE TABLE Local_Doctors (
    doctor_id SERIAL PRIMARY KEY,
    clinic_number VARCHAR(20) UNIQUE NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    telephone VARCHAR(20) NOT NULL
);

-- Patients table
CREATE TABLE Patients (
    patient_number VARCHAR(10) PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    address TEXT NOT NULL,
    telephone VARCHAR(20),
    date_of_birth DATE NOT NULL,
    sex CHAR(1) CHECK (sex IN ('M', 'F')),
    marital_status VARCHAR(20),
    date_registered DATE NOT NULL,
    doctor_id INTEGER REFERENCES Local_Doctors(doctor_id)
);

-- Next of Kin table
CREATE TABLE Next_Of_Kin (
    kin_id SERIAL PRIMARY KEY,
    patient_number VARCHAR(10) REFERENCES Patients(patient_number),
    fullname VARCHAR(100) NOT NULL,
    relationship VARCHAR(50) NOT NULL,
    address TEXT NOT NULL,
    telephone VARCHAR(20)
);

-- Appointment table
CREATE TABLE Appointments (
    appointment_number VARCHAR(20) PRIMARY KEY,
    patient_number VARCHAR(10) REFERENCES Patients(patient_number),
    staff_number VARCHAR(10) REFERENCES Staff(staff_number),
    date_time TIMESTAMP NOT NULL,
    examination_room VARCHAR(20) NOT NULL
);

-- Patient Status (Out-patient or In-patient)
CREATE TABLE Patient_Status (
    status_id SERIAL PRIMARY KEY,
    patient_number VARCHAR(10) REFERENCES Patients(patient_number) UNIQUE,
    is_outpatient BOOLEAN NOT NULL,
    waiting_list_date DATE,
    required_ward VARCHAR(10) REFERENCES Wards(ward_number),
    expected_duration INTEGER, -- in days
    date_placed DATE,
    expected_leave_date DATE,
    actual_leave_date DATE,
    bed_number VARCHAR(10)
);

-- Pharmaceutical Supplies table
CREATE TABLE Pharmaceutical_Supplies (
    drug_number VARCHAR(10) PRIMARY KEY,
    drug_name VARCHAR(100) NOT NULL,
    description TEXT,
    dosage VARCHAR(50) NOT NULL,
    method_of_administration VARCHAR(50) NOT NULL,
    quantity_in_stock INTEGER NOT NULL,
    reorder_level INTEGER NOT NULL,
    cost_per_unit DECIMAL(10, 2) NOT NULL
);

-- Surgical Supplies table
CREATE TABLE Surgical_Supplies (
    item_number VARCHAR(10) PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    description TEXT,
    quantity_in_stock INTEGER NOT NULL,
    reorder_level INTEGER NOT NULL,
    cost_per_unit DECIMAL(10, 2) NOT NULL,
    is_surgical BOOLEAN NOT NULL -- True for surgical, False for non-surgical
);

-- Patient Medication table
CREATE TABLE Patient_Medication (
    medication_id SERIAL PRIMARY KEY,
    patient_number VARCHAR(10) REFERENCES Patients(patient_number),
    drug_number VARCHAR(10) REFERENCES Pharmaceutical_Supplies(drug_number),
    units_per_day INTEGER NOT NULL,
    start_date DATE NOT NULL,
    finish_date DATE
);

-- Suppliers table
CREATE TABLE Suppliers (
    supplier_number VARCHAR(10) PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    fax VARCHAR(20)
);

-- Supply Items table (linking suppliers with items they supply)
CREATE TABLE Supply_Items (
    supply_id SERIAL PRIMARY KEY,
    supplier_number VARCHAR(10) REFERENCES Suppliers(supplier_number),
    item_number VARCHAR(10) REFERENCES Surgical_Supplies(item_number),
    drug_number VARCHAR(10) REFERENCES Pharmaceutical_Supplies(drug_number),
    CHECK ((item_number IS NOT NULL AND drug_number IS NULL) OR 
           (item_number IS NULL AND drug_number IS NOT NULL))
);

-- Requisitions table
CREATE TABLE Requisitions (
    requisition_number VARCHAR(20) PRIMARY KEY,
    ward_number VARCHAR(10) REFERENCES Wards(ward_number),
    staff_number VARCHAR(10) REFERENCES Staff(staff_number),
    requisition_date DATE NOT NULL,
    delivery_date DATE,
    signed_by VARCHAR(10) REFERENCES Staff(staff_number)
);

-- Requisition Items table
CREATE TABLE Requisition_Items (
    requisition_item_id SERIAL PRIMARY KEY,
    requisition_number VARCHAR(20) REFERENCES Requisitions(requisition_number),
    item_number VARCHAR(10) REFERENCES Surgical_Supplies(item_number),
    drug_number VARCHAR(10) REFERENCES Pharmaceutical_Supplies(drug_number),
    quantity INTEGER NOT NULL,
    CHECK ((item_number IS NOT NULL AND drug_number IS NULL) OR 
           (item_number IS NULL AND drug_number IS NOT NULL))
);

-- Create indexes for performance
CREATE INDEX idx_staff_ward ON Staff(ward_allocated);
CREATE INDEX idx_patient_status_ward ON Patient_Status(required_ward);
CREATE INDEX idx_patient_medication_patient ON Patient_Medication(patient_number);
CREATE INDEX idx_requisition_ward ON Requisitions(ward_number);

-- Insert some sample data for Wards
INSERT INTO Wards (ward_number, ward_name, location, total_beds, telephone_extension) 
VALUES ('11', 'Orthopaedic', 'E Block', 15, '7711');

-- Insert sample data for Staff Positions
INSERT INTO Staff_Positions (position_name) 
VALUES ('Medical Director'), ('Charge Nurse'), ('Staff Nurse'), ('Nurse'), ('Consultant'), ('Auxiliary');

-- Insert sample data for Salary Scales
INSERT INTO Salary_Scales (scale_id, min_salary, max_salary) 
VALUES ('1A', 15000.00, 20000.00), ('1B', 17000.00, 22000.00), ('1C', 18500.00, 25000.00);
