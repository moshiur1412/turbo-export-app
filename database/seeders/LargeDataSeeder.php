<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\Salary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LargeDataSeeder extends Seeder
{
    private int $chunkSize = 10000;
    private array $statuses = ['present', 'absent', 'late', 'leave', 'holiday'];

    private array $departments = [
        ['name' => 'Software Development', 'code' => 'SWD'],
        ['name' => 'Database Administration', 'code' => 'DBA'],
        ['name' => 'Network Operations', 'code' => 'NET'],
        ['name' => 'Cybersecurity', 'code' => 'SEC'],
        ['name' => 'Cloud Infrastructure', 'code' => 'CLO'],
        ['name' => 'DevOps Engineering', 'code' => 'DOPS'],
        ['name' => 'Quality Assurance', 'code' => 'QA'],
        ['name' => 'Business Intelligence', 'code' => 'BI'],
        ['name' => 'Data Science', 'code' => 'DS'],
        ['name' => 'Machine Learning', 'code' => 'ML'],
        ['name' => 'Artificial Intelligence', 'code' => 'AI'],
        ['name' => 'Mobile Development', 'code' => 'MOB'],
        ['name' => 'Frontend Development', 'code' => 'FRT'],
        ['name' => 'Backend Development', 'code' => 'BAC'],
        ['name' => 'Full Stack Development', 'code' => 'FST'],
        ['name' => 'UI/UX Design', 'code' => 'UID'],
        ['name' => 'Product Management', 'code' => 'PM'],
        ['name' => 'Project Management', 'code' => 'PRM'],
        ['name' => 'Scrum Master', 'code' => 'SCR'],
        ['name' => 'Technical Writing', 'code' => 'TWR'],
        ['name' => 'System Administration', 'code' => 'SYS'],
        ['name' => 'IT Support', 'code' => 'SUP'],
        ['name' => 'Help Desk', 'code' => 'HDS'],
        ['name' => 'IT Procurement', 'code' => 'PRC'],
        ['name' => 'Vendor Management', 'code' => 'VND'],
        ['name' => 'Outsourcing Management', 'code' => 'OUT'],
        ['name' => 'BPO Operations', 'code' => 'BPO'],
        ['name' => 'Call Center Operations', 'code' => 'CAL'],
        ['name' => 'Customer Success', 'code' => 'CSU'],
        ['name' => 'Sales & Marketing', 'code' => 'SAL'],
        ['name' => 'Digital Marketing', 'code' => 'DGM'],
        ['name' => 'Content Management', 'code' => 'CM'],
        ['name' => 'Social Media', 'code' => 'SMM'],
        ['name' => 'SEO & SEM', 'code' => 'SEO'],
        ['name' => 'Graphic Design', 'code' => 'GRD'],
        ['name' => 'Video Production', 'code' => 'VID'],
        ['name' => 'Human Resources', 'code' => 'HR'],
        ['name' => 'Talent Acquisition', 'code' => 'TAL'],
        ['name' => 'Learning & Development', 'code' => 'LND'],
        ['name' => 'Compensation & Benefits', 'code' => 'CBP'],
        ['name' => 'Employee Relations', 'code' => 'EMP'],
        ['name' => 'HR Analytics', 'code' => 'HRA'],
        ['name' => 'Finance & Accounting', 'code' => 'FIN'],
        ['name' => 'Budgeting & Planning', 'code' => 'BUD'],
        ['name' => 'Tax & Compliance', 'code' => 'TAX'],
        ['name' => 'Internal Audit', 'code' => 'AUD'],
        ['name' => 'Treasury Operations', 'code' => 'TRS'],
        ['name' => 'Payroll', 'code' => 'PAY'],
        ['name' => 'Legal & Compliance', 'code' => 'LGL'],
        ['name' => 'Corporate Governance', 'code' => 'CG'],
        ['name' => 'Risk Management', 'code' => 'RSK'],
        ['name' => 'Business Continuity', 'code' => 'BCM'],
        ['name' => 'Facilities Management', 'code' => 'FAC'],
        ['name' => 'Procurement', 'code' => 'PRO'],
        ['name' => 'Supply Chain', 'code' => 'SCM'],
        ['name' => 'Logistics', 'code' => 'LOG'],
        ['name' => 'Warehouse Operations', 'code' => 'WHO'],
        ['name' => 'Inventory Management', 'code' => 'INV'],
        ['name' => 'Operations Management', 'code' => 'OPS'],
        ['name' => 'Process Optimization', 'code' => 'POC'],
        ['name' => 'Lean Six Sigma', 'code' => 'LSS'],
        ['name' => 'Research & Development', 'code' => 'RD'],
        ['name' => 'Innovation Lab', 'code' => 'INL'],
        ['name' => 'Product Design', 'code' => 'PRD'],
        ['name' => 'Solutions Architecture', 'code' => 'SAA'],
        ['name' => 'Enterprise Architecture', 'code' => 'EAA'],
        ['name' => 'Technical Architecture', 'code' => 'TAA'],
        ['name' => 'Integration Services', 'code' => 'INT'],
        ['name' => 'API Development', 'code' => 'API'],
        ['name' => 'Microservices', 'code' => 'MIC'],
        ['name' => 'Blockchain Development', 'code' => 'BCK'],
        ['name' => 'IoT Engineering', 'code' => 'IOT'],
        ['name' => 'Embedded Systems', 'code' => 'EMB'],
        ['name' => 'Game Development', 'code' => 'GME'],
        ['name' => 'Augmented Reality', 'code' => 'AR'],
        ['name' => 'Virtual Reality', 'code' => 'VR'],
        ['name' => 'Big Data Engineering', 'code' => 'BDE'],
        ['name' => 'Data Engineering', 'code' => 'DE'],
        ['name' => 'Data Analytics', 'code' => 'DAN'],
        ['name' => 'Business Analysis', 'code' => 'BA'],
        ['name' => 'Requirements Engineering', 'code' => 'RE'],
        ['name' => 'Systems Analysis', 'code' => 'SA'],
        ['name' => 'Change Management', 'code' => 'CMG'],
        ['name' => 'IT Governance', 'code' => 'ITG'],
        ['name' => 'Security Operations', 'code' => 'SOC'],
        ['name' => 'Penetration Testing', 'code' => 'PEN'],
        ['name' => 'Security Architecture', 'code' => 'SRA'],
        ['name' => 'Identity Management', 'code' => 'IDM'],
        ['name' => 'Compliance & Privacy', 'code' => 'CPR'],
        ['name' => 'Disaster Recovery', 'code' => 'DR'],
        ['name' => 'Performance Engineering', 'code' => 'PE'],
        ['name' => 'Site Reliability', 'code' => 'SRE'],
        ['name' => 'Platform Engineering', 'code' => 'PLE'],
        ['name' => 'Infrastructure Engineering', 'code' => 'IE'],
        ['name' => 'Network Security', 'code' => 'NSEC'],
        ['name' => 'Telecommunications', 'code' => 'TEL'],
        ['name' => 'VoIP Engineering', 'code' => 'VOIP'],
        ['name' => 'Video Conferencing', 'code' => 'VIDC'],
        ['name' => 'Collaboration Tools', 'code' => 'COL'],
        ['name' => 'Enterprise Systems', 'code' => 'ESY'],
        ['name' => 'ERP Administration', 'code' => 'ERP'],
        ['name' => 'CRM Administration', 'code' => 'CRM'],
        ['name' => 'SaaS Operations', 'code' => 'SAAS'],
        ['name' => 'Managed Services', 'code' => 'MSP'],
        ['name' => 'Professional Services', 'code' => 'PS'],
        ['name' => 'Consulting Services', 'code' => 'CON'],
    ];

    private array $designations = [
        ['name' => 'Junior Software Engineer', 'code' => 'JSE', 'min_salary' => 25000, 'max_salary' => 45000],
        ['name' => 'Software Engineer', 'code' => 'SE', 'min_salary' => 40000, 'max_salary' => 70000],
        ['name' => 'Senior Software Engineer', 'code' => 'SSE', 'min_salary' => 65000, 'max_salary' => 100000],
        ['name' => 'Staff Software Engineer', 'code' => 'SSE2', 'min_salary' => 95000, 'max_salary' => 140000],
        ['name' => 'Principal Software Engineer', 'code' => 'PSE', 'min_salary' => 130000, 'max_salary' => 180000],
        ['name' => 'Distinguished Engineer', 'code' => 'DSE', 'min_salary' => 170000, 'max_salary' => 250000],
        ['name' => 'Software Architect', 'code' => 'SA', 'min_salary' => 120000, 'max_salary' => 200000],
        ['name' => 'Senior Software Architect', 'code' => 'SSA', 'min_salary' => 180000, 'max_salary' => 280000],
        ['name' => 'Solutions Architect', 'code' => 'SOA', 'min_salary' => 150000, 'max_salary' => 220000],
        ['name' => 'Enterprise Architect', 'code' => 'EA', 'min_salary' => 160000, 'max_salary' => 250000],
        ['name' => 'Technical Lead', 'code' => 'TL', 'min_salary' => 90000, 'max_salary' => 150000],
        ['name' => 'Engineering Manager', 'code' => 'EM', 'min_salary' => 120000, 'max_salary' => 200000],
        ['name' => 'Senior Engineering Manager', 'code' => 'SEM', 'min_salary' => 180000, 'max_salary' => 280000],
        ['name' => 'Director of Engineering', 'code' => 'DOE', 'min_salary' => 200000, 'max_salary' => 350000],
        ['name' => 'VP of Engineering', 'code' => 'VPE', 'min_salary' => 250000, 'max_salary' => 450000],
        ['name' => 'CTO', 'code' => 'CTO', 'min_salary' => 300000, 'max_salary' => 600000],
        ['name' => 'Junior Database Administrator', 'code' => 'JDBA', 'min_salary' => 30000, 'max_salary' => 50000],
        ['name' => 'Database Administrator', 'code' => 'DBA', 'min_salary' => 50000, 'max_salary' => 90000],
        ['name' => 'Senior Database Administrator', 'code' => 'SDBA', 'min_salary' => 85000, 'max_salary' => 130000],
        ['name' => 'Database Architect', 'code' => 'DBA2', 'min_salary' => 120000, 'max_salary' => 180000],
        ['name' => 'Junior Network Engineer', 'code' => 'JNE', 'min_salary' => 28000, 'max_salary' => 45000],
        ['name' => 'Network Engineer', 'code' => 'NE', 'min_salary' => 45000, 'max_salary' => 80000],
        ['name' => 'Senior Network Engineer', 'code' => 'SNE', 'min_salary' => 75000, 'max_salary' => 120000],
        ['name' => 'Network Architect', 'code' => 'NA', 'min_salary' => 110000, 'max_salary' => 170000],
        ['name' => 'Junior Security Analyst', 'code' => 'JSA', 'min_salary' => 35000, 'max_salary' => 55000],
        ['name' => 'Security Analyst', 'code' => 'SA2', 'min_salary' => 55000, 'max_salary' => 95000],
        ['name' => 'Senior Security Analyst', 'code' => 'SSA2', 'min_salary' => 90000, 'max_salary' => 140000],
        ['name' => 'Security Engineer', 'code' => 'SE2', 'min_salary' => 70000, 'max_salary' => 120000],
        ['name' => 'Senior Security Engineer', 'code' => 'SSE3', 'min_salary' => 115000, 'max_salary' => 170000],
        ['name' => 'Security Architect', 'code' => 'SCA', 'min_salary' => 140000, 'max_salary' => 220000],
        ['name' => 'Penetration Tester', 'code' => 'PT', 'min_salary' => 70000, 'max_salary' => 130000],
        ['name' => 'Senior Penetration Tester', 'code' => 'SPT', 'min_salary' => 125000, 'max_salary' => 180000],
        ['name' => 'SOC Analyst', 'code' => 'SOCA', 'min_salary' => 50000, 'max_salary' => 85000],
        ['name' => 'Senior SOC Analyst', 'code' => 'SSOCA', 'min_salary' => 80000, 'max_salary' => 120000],
        ['name' => 'Junior DevOps Engineer', 'code' => 'JDE', 'min_salary' => 40000, 'max_salary' => 65000],
        ['name' => 'DevOps Engineer', 'code' => 'DE2', 'min_salary' => 65000, 'max_salary' => 110000],
        ['name' => 'Senior DevOps Engineer', 'code' => 'SDE2', 'min_salary' => 105000, 'max_salary' => 160000],
        ['name' => 'Platform Engineer', 'code' => 'PLE2', 'min_salary' => 90000, 'max_salary' => 150000],
        ['name' => 'Senior Platform Engineer', 'code' => 'SPLE', 'min_salary' => 145000, 'max_salary' => 210000],
        ['name' => 'SRE Engineer', 'code' => 'SRE2', 'min_salary' => 85000, 'max_salary' => 140000],
        ['name' => 'Senior SRE Engineer', 'code' => 'SSRE', 'min_salary' => 135000, 'max_salary' => 200000],
        ['name' => 'Site Reliability Manager', 'code' => 'SRM', 'min_salary' => 150000, 'max_salary' => 220000],
        ['name' => 'Junior QA Engineer', 'code' => 'JQA', 'min_salary' => 25000, 'max_salary' => 45000],
        ['name' => 'QA Engineer', 'code' => 'QA', 'min_salary' => 45000, 'max_salary' => 80000],
        ['name' => 'Senior QA Engineer', 'code' => 'SQA', 'min_salary' => 75000, 'max_salary' => 120000],
        ['name' => 'QA Lead', 'code' => 'QAL', 'min_salary' => 100000, 'max_salary' => 160000],
        ['name' => 'QA Manager', 'code' => 'QAM', 'min_salary' => 120000, 'max_salary' => 180000],
        ['name' => 'Test Automation Engineer', 'code' => 'TAE', 'min_salary' => 60000, 'max_salary' => 100000],
        ['name' => 'Senior Test Automation Engineer', 'code' => 'STAE', 'min_salary' => 95000, 'max_salary' => 150000],
        ['name' => 'Performance Test Engineer', 'code' => 'PTE', 'min_salary' => 70000, 'max_salary' => 120000],
        ['name' => 'Junior Data Scientist', 'code' => 'JDS', 'min_salary' => 45000, 'max_salary' => 75000],
        ['name' => 'Data Scientist', 'code' => 'DS', 'min_salary' => 75000, 'max_salary' => 130000],
        ['name' => 'Senior Data Scientist', 'code' => 'SDS', 'min_salary' => 125000, 'max_salary' => 190000],
        ['name' => 'Lead Data Scientist', 'code' => 'LDS', 'min_salary' => 180000, 'max_salary' => 280000],
        ['name' => 'ML Engineer', 'code' => 'MLE', 'min_salary' => 90000, 'max_salary' => 150000],
        ['name' => 'Senior ML Engineer', 'code' => 'SMLE', 'min_salary' => 145000, 'max_salary' => 220000],
        ['name' => 'MLOps Engineer', 'code' => 'MLOPS', 'min_salary' => 100000, 'max_salary' => 170000],
        ['name' => 'AI Research Scientist', 'code' => 'AIRS', 'min_salary' => 120000, 'max_salary' => 200000],
        ['name' => 'Senior AI Research Scientist', 'code' => 'SAIRS', 'min_salary' => 190000, 'max_salary' => 300000],
        ['name' => 'Junior Frontend Developer', 'code' => 'JFD', 'min_salary' => 28000, 'max_salary' => 50000],
        ['name' => 'Frontend Developer', 'code' => 'FD', 'min_salary' => 50000, 'max_salary' => 90000],
        ['name' => 'Senior Frontend Developer', 'code' => 'SFD', 'min_salary' => 85000, 'max_salary' => 140000],
        ['name' => 'Frontend Architect', 'code' => 'FEA', 'min_salary' => 130000, 'max_salary' => 200000],
        ['name' => 'Junior Backend Developer', 'code' => 'JBD', 'min_salary' => 30000, 'max_salary' => 55000],
        ['name' => 'Backend Developer', 'code' => 'BD', 'min_salary' => 55000, 'max_salary' => 95000],
        ['name' => 'Senior Backend Developer', 'code' => 'SBD', 'min_salary' => 90000, 'max_salary' => 150000],
        ['name' => 'Backend Architect', 'code' => 'BKA', 'min_salary' => 140000, 'max_salary' => 220000],
        ['name' => 'Junior Full Stack Developer', 'code' => 'JFSD', 'min_salary' => 32000, 'max_salary' => 55000],
        ['name' => 'Full Stack Developer', 'code' => 'FSD', 'min_salary' => 55000, 'max_salary' => 95000],
        ['name' => 'Senior Full Stack Developer', 'code' => 'SFSD', 'min_salary' => 90000, 'max_salary' => 150000],
        ['name' => 'Junior Mobile Developer', 'code' => 'JMD', 'min_salary' => 35000, 'max_salary' => 60000],
        ['name' => 'Mobile Developer', 'code' => 'MD', 'min_salary' => 60000, 'max_salary' => 100000],
        ['name' => 'Senior Mobile Developer', 'code' => 'SMD', 'min_salary' => 95000, 'max_salary' => 160000],
        ['name' => 'React Native Developer', 'code' => 'RND', 'min_salary' => 65000, 'max_salary' => 120000],
        ['name' => 'Flutter Developer', 'code' => 'FLD', 'min_salary' => 70000, 'max_salary' => 130000],
        ['name' => 'iOS Developer', 'code' => 'IOS', 'min_salary' => 60000, 'max_salary' => 110000],
        ['name' => 'Android Developer', 'code' => 'AND', 'min_salary' => 60000, 'max_salary' => 110000],
        ['name' => 'UI Designer', 'code' => 'UID', 'min_salary' => 40000, 'max_salary' => 80000],
        ['name' => 'Senior UI Designer', 'code' => 'SUI', 'min_salary' => 75000, 'max_salary' => 120000],
        ['name' => 'UX Designer', 'code' => 'UXD', 'min_salary' => 45000, 'max_salary' => 90000],
        ['name' => 'Senior UX Designer', 'code' => 'SUXD', 'min_salary' => 85000, 'max_salary' => 140000],
        ['name' => 'Product Designer', 'code' => 'PRD2', 'min_salary' => 60000, 'max_salary' => 120000],
        ['name' => 'Senior Product Designer', 'code' => 'SPRD', 'min_salary' => 115000, 'max_salary' => 180000],
        ['name' => 'UX Researcher', 'code' => 'UXR', 'min_salary' => 50000, 'max_salary' => 95000],
        ['name' => 'Senior UX Researcher', 'code' => 'SUXR', 'min_salary' => 90000, 'max_salary' => 150000],
        ['name' => 'Junior Business Analyst', 'code' => 'JBA', 'min_salary' => 35000, 'max_salary' => 60000],
        ['name' => 'Business Analyst', 'code' => 'BA', 'min_salary' => 60000, 'max_salary' => 100000],
        ['name' => 'Senior Business Analyst', 'code' => 'SBA', 'min_salary' => 95000, 'max_salary' => 150000],
        ['name' => 'Lead Business Analyst', 'code' => 'LBA', 'min_salary' => 140000, 'max_salary' => 200000],
        ['name' => 'Junior Product Manager', 'code' => 'JPM', 'min_salary' => 55000, 'max_salary' => 90000],
        ['name' => 'Product Manager', 'code' => 'PM', 'min_salary' => 90000, 'max_salary' => 150000],
        ['name' => 'Senior Product Manager', 'code' => 'SPM', 'min_salary' => 145000, 'max_salary' => 220000],
        ['name' => 'Principal Product Manager', 'code' => 'PPM', 'min_salary' => 210000, 'max_salary' => 320000],
        ['name' => 'Associate Project Manager', 'code' => 'APM', 'min_salary' => 40000, 'max_salary' => 70000],
        ['name' => 'Project Manager', 'code' => 'PM2', 'min_salary' => 70000, 'max_salary' => 120000],
        ['name' => 'Senior Project Manager', 'code' => 'SPM2', 'min_salary' => 115000, 'max_salary' => 180000],
        ['name' => 'Scrum Master', 'code' => 'SM', 'min_salary' => 55000, 'max_salary' => 95000],
        ['name' => 'Senior Scrum Master', 'code' => 'SSM', 'min_salary' => 90000, 'max_salary' => 140000],
        ['name' => 'Agile Coach', 'code' => 'AC', 'min_salary' => 100000, 'max_salary' => 170000],
        ['name' => 'Junior Data Engineer', 'code' => 'JDE2', 'min_salary' => 50000, 'max_salary' => 85000],
        ['name' => 'Data Engineer', 'code' => 'DE', 'min_salary' => 85000, 'max_salary' => 140000],
        ['name' => 'Senior Data Engineer', 'code' => 'SDE', 'min_salary' => 135000, 'max_salary' => 200000],
        ['name' => 'Big Data Engineer', 'code' => 'BDE', 'min_salary' => 100000, 'max_salary' => 170000],
        ['name' => 'Senior Big Data Engineer', 'code' => 'SBDE', 'min_salary' => 165000, 'max_salary' => 250000],
        ['name' => 'Junior Cloud Engineer', 'code' => 'JCE', 'min_salary' => 50000, 'max_salary' => 85000],
        ['name' => 'Cloud Engineer', 'code' => 'CE', 'min_salary' => 85000, 'max_salary' => 140000],
        ['name' => 'Senior Cloud Engineer', 'code' => 'SCE', 'min_salary' => 135000, 'max_salary' => 200000],
        ['name' => 'Cloud Architect', 'code' => 'CLA', 'min_salary' => 160000, 'max_salary' => 250000],
        ['name' => 'AWS Solutions Architect', 'code' => 'AWSSA', 'min_salary' => 130000, 'max_salary' => 200000],
        ['name' => 'Azure Solutions Architect', 'code' => 'AZSA', 'min_salary' => 135000, 'max_salary' => 210000],
        ['name' => 'GCP Solutions Architect', 'code' => 'GCPSA', 'min_salary' => 140000, 'max_salary' => 220000],
        ['name' => 'Junior Systems Administrator', 'code' => 'JSA2', 'min_salary' => 30000, 'max_salary' => 50000],
        ['name' => 'Systems Administrator', 'code' => 'SYSA', 'min_salary' => 50000, 'max_salary' => 85000],
        ['name' => 'Senior Systems Administrator', 'code' => 'SSYSA', 'min_salary' => 80000, 'max_salary' => 130000],
        ['name' => 'Systems Engineer', 'code' => 'SE3', 'min_salary' => 55000, 'max_salary' => 95000],
        ['name' => 'Senior Systems Engineer', 'code' => 'SSE4', 'min_salary' => 90000, 'max_salary' => 145000],
        ['name' => 'Infrastructure Engineer', 'code' => 'IE', 'min_salary' => 70000, 'max_salary' => 120000],
        ['name' => 'Senior Infrastructure Engineer', 'code' => 'SIE', 'min_salary' => 115000, 'max_salary' => 180000],
    ];

    public function run(): void
    {
        $this->command->info('Starting 10M+ data seeding (for initial testing)...');
        $this->command->info('This will create 100 departments, 120 designations, 10K employees, and 10M+ records.');
        
        $this->generateBaseData();
        $this->generateAttendanceData();
        $this->generateLeaveData();
        $this->generateLeaveBalances();
        
        $this->command->info('Data generation complete!');
        $this->printSummary();
    }

    private function generateBaseData(): void
    {
        $this->command->info('Generating base data (departments, designations, users, salaries)...');
        
        $departments = [];
        foreach ($this->departments as $dept) {
            $departments[] = array_merge($dept, ['created_at' => now(), 'updated_at' => now()]);
        }
        Department::insert($departments);

        $designations = [];
        foreach ($this->designations as $desig) {
            $designations[] = array_merge($desig, ['created_at' => now(), 'updated_at' => now()]);
        }
        Designation::insert($designations);

        $totalDepts = Department::count();
        $totalDesigs = Designation::count();
        
        $this->command->info("Created {$totalDepts} departments and {$totalDesigs} designations");
        $this->command->info('Creating 10,000 employees...');

        $batchSize = 10000;
        $totalEmployees = 10000;
        
        for ($batch = 0; $batch < $totalEmployees / $batchSize; $batch++) {
            $users = [];
            $salaries = [];
            
            for ($i = 0; $i < $batchSize; $i++) {
                $num = $batch * $batchSize + $i + 1;
                $deptId = rand(1, $totalDepts);
                $desigId = rand(1, $totalDesigs);
                
                $basicSalary = rand(20000, 150000);
                $houseRent = $basicSalary * 0.3;
                $medical = $basicSalary * 0.1;
                $transport = $basicSalary * 0.1;
                $special = $basicSalary * 0.15;
                $provident = $basicSalary * 0.12;
                $tax = $basicSalary * 0.05;
                $gross = $basicSalary + $houseRent + $medical + $transport + $special;
                $net = $gross - $provident - $tax;
                
                $users[] = [
                    'employee_id' => 'EMP' . str_pad((string)$num, 6, '0', STR_PAD_LEFT),
                    'name' => "Employee {$num}",
                    'email' => "employee{$num}@example.com",
                    'password' => bcrypt('password'),
                    'department_id' => $deptId,
                    'designation_id' => $desigId,
                    'join_date' => Carbon::now()->subMonths(rand(1, 60)),
                    'status' => rand(1, 100) <= 95 ? 'active' : (rand(1, 2) == 1 ? 'inactive' : 'on_leave'),
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                $salaries[] = [
                    'user_id' => $batch * $batchSize + $i + 1,
                    'basic_salary' => $basicSalary,
                    'house_rent' => $houseRent,
                    'medical_allowance' => $medical,
                    'transport_allowance' => $transport,
                    'special_allowance' => $special,
                    'provident_fund' => $provident,
                    'tax' => $tax,
                    'gross_salary' => $gross,
                    'net_salary' => $net,
                    'is_active' => true,
                    'effective_date' => Carbon::now()->subMonths(rand(1, 12)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            User::insert($users);
            Salary::insert($salaries);
            
            $progress = round((($batch + 1) / ($totalEmployees / $batchSize)) * 100);
            $this->command->info("  Employees: {$progress}% ({$num}/{$totalEmployees})");
        }
        
        $this->command->info('Base data generation complete.');
    }

    private function generateAttendanceData(): void
    {
        $this->command->info('Generating attendance data (6 months)...');
        
        $totalEmployees = User::count();
        $months = 6;
        $daysPerMonth = 26;
        $recordsPerEmployee = $months * $daysPerMonth;
        $totalRecords = $totalEmployees * $recordsPerEmployee;
        
        $this->command->info("Target: " . number_format($totalRecords) . " attendance records");
        
        $bar = $this->command->getOutput()->createProgressBar($totalRecords);
        $bar->start();
        
        $batch = [];
        
        for ($month = $months; $month >= 1; $month--) {
            $monthStart = Carbon::now()->subMonths($month)->startOfMonth();
            
            for ($day = 0; $day < $daysPerMonth; $day++) {
                $date = $monthStart->copy()->addDays($day);
                
                if ($date->isWeekend()) {
                    continue;
                }
                
                for ($userId = 1; $userId <= $totalEmployees; $userId++) {
                    $batch[] = $this->generateAttendanceRecord($userId, $date);
                    
                    if (count($batch) >= $this->chunkSize) {
                        Attendance::insert($batch);
                        $batch = [];
                        $bar->advance($this->chunkSize);
                    }
                }
            }
        }
        
        if (!empty($batch)) {
            Attendance::insert($batch);
            $bar->advance(count($batch));
        }
        
        $bar->finish();
        $this->command->info('');
        $this->command->info('Attendance records: ' . number_format(Attendance::count()));
    }

    private function generateAttendanceRecord(int $userId, Carbon $date): array
    {
        $rand = rand(1, 100);
        
        if ($rand <= 75) {
            return [
                'user_id' => $userId,
                'attendance_date' => $date->format('Y-m-d'),
                'check_in' => sprintf('%02d:%02d', rand(8, 10), rand(0, 59)),
                'check_out' => sprintf('%02d:%02d', rand(17, 20), rand(0, 59)),
                'worked_hours' => rand(7, 10) + (rand(0, 59) / 60),
                'status' => 'present',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        } elseif ($rand <= 90) {
            return [
                'user_id' => $userId,
                'attendance_date' => $date->format('Y-m-d'),
                'check_in' => sprintf('%02d:%02d', rand(9, 12), rand(0, 59)),
                'check_out' => sprintf('%02d:%02d', rand(14, 17), rand(0, 59)),
                'worked_hours' => rand(4, 6) + (rand(0, 59) / 60),
                'status' => 'present',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        } elseif ($rand <= 95) {
            return [
                'user_id' => $userId,
                'attendance_date' => $date->format('Y-m-d'),
                'check_in' => sprintf('%02d:%02d', rand(10, 12), rand(0, 59)),
                'check_out' => sprintf('%02d:%02d', rand(17, 20), rand(0, 59)),
                'worked_hours' => rand(7, 9) + (rand(0, 59) / 60),
                'status' => 'late',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        } elseif ($rand <= 98) {
            return [
                'user_id' => $userId,
                'attendance_date' => $date->format('Y-m-d'),
                'check_in' => null,
                'check_out' => null,
                'worked_hours' => 0,
                'status' => 'absent',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        } else {
            return [
                'user_id' => $userId,
                'attendance_date' => $date->format('Y-m-d'),
                'check_in' => null,
                'check_out' => null,
                'worked_hours' => 0,
                'status' => 'leave',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    private function generateLeaveData(): void
    {
        $this->command->info('Generating leave records (100K records)...');
        
        $totalEmployees = User::count();
        $totalLeaves = 100000;
        
        $bar = $this->command->getOutput()->createProgressBar($totalLeaves);
        $bar->start();
        
        $batch = [];
        $statuses = ['approved', 'approved', 'approved', 'approved', 'pending', 'rejected'];
        
        for ($userId = 1; $userId <= $totalEmployees; $userId++) {
            for ($i = 0; $i < 10; $i++) {
                $startDate = Carbon::now()->subMonths(rand(0, 6))->startOfMonth()->addDays(rand(0, 25));
                $days = rand(1, 10);
                $status = $statuses[array_rand($statuses)];
                
                $batch[] = [
                    'user_id' => $userId,
                    'leave_type' => ['casual', 'sick', 'annual', 'unpaid'][rand(0, 3)],
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $startDate->copy()->addDays($days - 1)->format('Y-m-d'),
                    'days' => $days,
                    'reason' => 'Personal reason',
                    'status' => $status,
                    'is_paid' => $status === 'approved' && rand(1, 100) <= 70,
                    'approved_by' => $status === 'approved' ? rand(1, 10) : null,
                    'approved_at' => $status === 'approved' ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                if (count($batch) >= $this->chunkSize) {
                    Leave::insert($batch);
                    $batch = [];
                    $bar->advance($this->chunkSize);
                }
            }
        }
        
        if (!empty($batch)) {
            Leave::insert($batch);
            $bar->advance(count($batch));
        }
        
        $bar->finish();
        $this->command->info('');
        $this->command->info('Leave records: ' . number_format(Leave::count()));
    }

    private function generateLeaveBalances(): void
    {
        $this->command->info('Generating leave balance records (120K records)...');
        
        $totalEmployees = User::count();
        $years = 1;
        
        $bar = $this->command->getOutput()->createProgressBar();
        $bar->setMaxSteps($totalEmployees * $years * 12);
        $bar->start();
        
        $batch = [];
        $currentYear = Carbon::now()->year;
        
        for ($yearOffset = $years - 1; $yearOffset >= 0; $yearOffset--) {
            $year = $currentYear - $yearOffset;
            
            for ($month = 1; $month <= 12; $month++) {
                for ($userId = 1; $userId <= $totalEmployees; $userId++) {
                    $batch[] = [
                        'user_id' => $userId,
                        'year' => $year,
                        'month' => $month,
                        'casual_leave' => 2.0,
                        'sick_leave' => 1.0,
                        'annual_leave' => 2.0,
                        'used_casual' => rand(0, 2),
                        'used_sick' => rand(0, 1),
                        'used_annual' => rand(0, 2),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    if (count($batch) >= $this->chunkSize) {
                        LeaveBalance::insert($batch);
                        $batch = [];
                        $bar->advance($this->chunkSize);
                    }
                }
            }
        }
        
        if (!empty($batch)) {
            LeaveBalance::insert($batch);
            $bar->advance(count($batch));
        }
        
        $bar->finish();
        $this->command->info('');
        $this->command->info('Leave balance records: ' . number_format(LeaveBalance::count()));
    }

    private function printSummary(): void
    {
        $this->command->info('');
        $this->command->info('=== DATA GENERATION SUMMARY ===');
        $this->command->info('Departments:     ' . number_format(Department::count()));
        $this->command->info('Designations:    ' . number_format(Designation::count()));
        $this->command->info('Employees:       ' . number_format(User::count()));
        $this->command->info('Salaries:        ' . number_format(Salary::count()));
        $this->command->info('Attendance:      ' . number_format(Attendance::count()));
        $this->command->info('Leaves:          ' . number_format(Leave::count()));
        $this->command->info('Leave Balances:  ' . number_format(LeaveBalance::count()));
        $this->command->info('================================');
    }
}
